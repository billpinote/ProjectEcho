<?php

namespace App\Domain\FlightPlans\Services;

use App\Auth\AuthenticationEvidence;
use App\Domain\FlightPlans\Enums\FlightPlanStatus;
use App\Domain\FlightPlans\Support\FilingDocumentRegistry;
use App\Domain\FlightPlans\Support\FlightAccess;
use App\Domain\FlightPlans\Support\PilotFlightPlanCredentials;
use App\Domain\Pilots\Enums\PilotLicenseType;
use App\Models\Flight;
use App\Models\FlightPlanEvent;
use App\Models\PilotSignature;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PicAuthorizationService
{
    private const HANDOFF_TTL_MINUTES = 30;

    private const ELIGIBLE_LICENSES = [
        PilotLicenseType::PrivatePilot,
        PilotLicenseType::CommercialPilot,
        PilotLicenseType::AirlineTransportPilot,
    ];

    public function selfFilingSignature(User $pic, ?int $expectedId = null): PilotSignature
    {
        try {
            $signature = app(FilingAuthorizationService::class)->approvedSignature($pic);
        } catch (ValidationException) {
            throw ValidationException::withMessages(['signature' => 'An approved pilot signature is required. Submit your signature for administrator approval before authorizing this filing.']);
        } catch (HttpException $exception) {
            if (! in_array($exception->getStatusCode(), [404, 409], true)) {
                throw $exception;
            }
            throw ValidationException::withMessages(['signature' => 'Your approved signature could not be verified. Contact an administrator before authorizing this filing. Your entered flight information has been preserved.']);
        }
        if ($expectedId !== null && $signature->getKey() !== $expectedId) {
            throw ValidationException::withMessages(['signature' => 'Your approved signature changed. Review and confirm the filing again.']);
        }

        return $signature;
    }

    public function authorizeSelfFiling(Flight $flight, User $pic, AuthenticationEvidence $evidence, int $signatureId, ?string $reviewedFingerprint = null, string $documentType = 'filing'): Flight
    {
        $evidence->assertActor($pic);

        return DB::transaction(function () use ($flight, $pic, $evidence, $signatureId, $reviewedFingerprint, $documentType): Flight {
            $flight = Flight::query()->lockForUpdate()->findOrFail($flight->id);
            $pic = User::query()->lockForUpdate()->findOrFail($pic->id);
            abort_unless($pic->is_active && $pic->isPilot() && $pic->can('view', $flight)
                && (int) $flight->pilot_in_command_user_id === (int) $pic->id, 403);
            if ($documentType !== 'filing') {
                throw ValidationException::withMessages(['signature' => 'Review and authorize the complete filing package; individual document signing is no longer available.']);
            }
            $this->selfFilingSignature($pic, $signatureId);
            $this->assertManifestForPic($flight, $pic, $this->eligibleCredentials($pic, $flight));
            $documents = app(FilingDocumentRegistry::class)->snapshots($flight);
            if ($reviewedFingerprint !== null && ! hash_equals($reviewedFingerprint, FilingDocumentRegistry::fingerprint($documents))) {
                throw ValidationException::withMessages(['signature' => 'The filing documents changed. Review and confirm the complete current package again.']);
            }
            $selfPrepared = (int) $flight->prepared_by_user_id === (int) $pic->id;
            $token = $this->createAuthorizationHandoff($flight, $selfPrepared);

            return $this->authorizeFromHandoff($token, $pic, $evidence, true, $selfPrepared);
        });
    }

    public function needsAuthorization(Flight $flight): bool
    {
        return in_array($flight->status, [FlightPlanStatus::AwaitingPic, FlightPlanStatus::Pending], true)
            && ! $flight->hasBeenAccepted() && ! $flight->isAvsecFrozen() && ! $flight->isPicAuthorizationDeclined()
            && ($flight->status === FlightPlanStatus::AwaitingPic || ! $flight->canSubmitToAtc());
    }

    public function canReviewFiling(User $actor, Flight $flight): bool
    {
        $selfPrepared = (int) $flight->prepared_by_user_id === (int) $actor->id
            && (int) $flight->pilot_in_command_user_id === (int) $actor->id;

        return $actor->is_active && $actor->isPilot() && $actor->can('view', $flight)
            && ($selfPrepared || ((int) $flight->prepared_by_user_id !== (int) $actor->id
                && FlightAccess::canAccessPicAuthorization($actor, $flight)))
            && $this->needsAuthorization($flight);
    }

    public function authorizationUnavailableReason(Flight $flight, User $actor): ?string
    {
        try {
            $this->assertManifestForPic($flight, $actor, $this->eligibleCredentials($actor, $flight));
            $this->selfFilingSignature($actor);
        } catch (ValidationException $exception) {
            return collect($exception->errors())->flatten()->implode(' ');
        }

        return null;
    }

    public function createAuthorizationHandoff(Flight $flight, bool $selfPrepared = false): string
    {
        return DB::transaction(function () use ($flight): string {
            $flight = Flight::query()->lockForUpdate()->findOrFail($flight->getKey());
            abort_unless($this->needsAuthorization($flight), 422);

            app(FilingAuthorizationService::class)->request($flight);
            $token = Str::random(64);
            $revision = (int) ($flight->revision_number ?? 1);
            $flight->forceFill([
                'pic_authorization_token' => hash('sha256', $token.'.'.$revision),
                'pic_authorization_token_expires_at' => now()->addMinutes(self::HANDOFF_TTL_MINUTES),
            ])->saveQuietly();

            return $token;
        });
    }

    public function resolveAuthorizationHandoff(string $token): ?Flight
    {
        if ($token === '' || ! preg_match('/^[A-Za-z0-9]+$/', $token)) {
            return null;
        }

        $flight = Flight::query()
            ->whereNotNull('pic_authorization_token')
            ->get()
            ->first(fn (Flight $candidate): bool => hash_equals(
                (string) $candidate->pic_authorization_token,
                hash('sha256', $token.'.'.((int) ($candidate->revision_number ?? 1))),
            ));

        if ($flight === null
            || $flight->pic_authorization_token_expires_at?->isPast()
            || ! $this->needsAuthorization($flight)
        ) {
            return null;
        }

        return $flight;
    }

    public function authorizeFromPayload(string $payload, User $authorizer, ?AuthenticationEvidence $evidence = null, bool $applySignature = true): Flight
    {
        $evidence?->assertActor($authorizer);
        $flight = $this->resolveAccessibleFlightFromPayload($payload, $authorizer);
        $token = $this->createAuthorizationHandoff($flight);

        return $this->authorizeFromHandoff($token, $authorizer, $evidence, $applySignature);
    }

    public function authorizeFromHandoff(string $token, User $authorizer, ?AuthenticationEvidence $evidence = null, bool $applySignature = true, bool $selfPrepared = false): Flight
    {
        $evidence?->assertActor($authorizer);
        $flight = $this->resolveAuthorizationHandoff($token);
        if ($flight === null) {
            throw ValidationException::withMessages(['payload' => 'This PIC authorization handoff is invalid, expired, or no longer current.']);
        }

        if ($selfPrepared) {
            abort_unless($authorizer->isPilot()
                && (int) $flight->prepared_by_user_id === (int) $authorizer->getKey()
                && (int) $flight->pilot_in_command_user_id === (int) $authorizer->getKey(), 403);
        }

        if (! $selfPrepared) {
            $this->guardPicAccess($flight, $authorizer);
        }
        $this->eligibleCredentials($authorizer, $flight);
        if (! $applySignature) {
            throw ValidationException::withMessages(['signature' => 'Authorize and sign the complete filing using your approved pilot signature.']);
        }

        $authorizedFlight = DB::transaction(function () use ($flight, $authorizer, $token, $evidence, $selfPrepared): Flight {
            $flight = Flight::query()->lockForUpdate()->findOrFail($flight->getKey());
            $authorizer = User::query()->lockForUpdate()->findOrFail($authorizer->getKey());
            if ($selfPrepared) {
                abort_unless($authorizer->isPilot()
                    && (int) $flight->prepared_by_user_id === (int) $authorizer->id
                    && (int) $flight->pilot_in_command_user_id === (int) $authorizer->id, 403);
            }
            if (! $selfPrepared) {
                $this->guardPicAccess($flight, $authorizer);
            } else {
                abort_unless(FlightAccess::canView($authorizer, $flight), 403);
            }
            $credentials = $this->eligibleCredentials($authorizer, $flight);
            $this->guardCurrentAuthorizationState($flight, $authorizer, ! $selfPrepared);
            $this->guardHandoffToken($flight, $token);
            $packages = app(FilingAuthorizationService::class);
            $package = $packages->assertReviewedPackageCurrent($flight);
            $this->assertManifestForPic($flight, $authorizer, $credentials);
            $signature = $packages->approvedSignature($authorizer);
            if ($package->signed_document_types !== null && array_diff(array_keys($package->documents), $package->signed_document_types) !== []) {
                throw ValidationException::withMessages(['payload' => 'Review the complete current filing package before authorizing.']);
            }

            $packages->recordingDecision($flight, fn () => $flight->forceFill([
                'pilot_in_command_user_id' => $authorizer->getKey(),
                'pilot_id' => $authorizer->getKey(),
                'pilot_in_command' => $credentials['pilot_name'],
                'pilot_license_no' => $credentials['license'],
                'pilot_ratings' => $credentials['ratings'],
                'license_expiry_date' => $credentials['license_expiry_date'],
                'pic_authorized_by_user_id' => $authorizer->getKey(),
                'pic_authorized_at' => now(),
                'pic_authorization_method' => 'QR',
                'pic_authorized_revision' => (int) ($flight->revision_number ?? 1),
                'pic_authorization_status' => 'authorized',
                'pic_authorization_declined_by_user_id' => null,
                'pic_authorization_declined_at' => null,
                'pic_authorization_decline_reason' => null,
                'pic_authorization_token' => null,
                'pic_authorization_token_expires_at' => null,
                'status' => FlightPlanStatus::Pending,
            ])->save());

            app(FlightManifestService::class)->assertComplete($flight);
            $packages->decide($package, $flight, $authorizer, true, $evidence, $signature);
            $ready = $packages->isReadyForAtmo($flight);
            $flight->forceFill(['status' => $ready ? FlightPlanStatus::Pending : FlightPlanStatus::AwaitingPic])->saveQuietly();

            FlightPlanEvent::record($flight, FlightPlanEvent::TYPE_PIC_AUTHORIZED, $authorizer, null, [
                'revision_number' => $flight->revision_number,
                'authorization_method' => 'QR',
                'filing_authorization_id' => $package->getKey(),
                ...($evidence === null ? [] : ['authentication' => $evidence->auditValues()]),
            ]);
            if ($ready) {
                FlightPlanEvent::record($flight, FlightPlanEvent::TYPE_SUBMITTED_TO_ATC, $authorizer);
            }

            return $flight->refresh();
        });

        try {
            app(FlightPlanPdfService::class)->regenerate($authorizedFlight);
        } catch (\Throwable $exception) {
            // Both authoritative signed PDFs are already preserved in the committed package.
            // A recoverable stored-PDF failure must not report a failed PIC decision after commit.
            report($exception);
        }

        return $authorizedFlight;
    }

    public function declineFromPayload(string $payload, User $user, ?string $reason = null, ?AuthenticationEvidence $evidence = null): Flight
    {
        $evidence?->assertActor($user);
        $flight = $this->resolveAccessibleFlightFromPayload($payload, $user);
        $token = $this->createAuthorizationHandoff($flight);

        return $this->declineFromHandoff($token, $user, $reason, $evidence);
    }

    public function declineFromHandoff(string $token, User $user, ?string $reason = null, ?AuthenticationEvidence $evidence = null): Flight
    {
        $evidence?->assertActor($user);
        $reason = filled($reason) ? trim($reason) : null;
        if ($reason === null || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['declineReason' => 'A reason for declining is required and must not exceed 500 characters.']);
        }
        $flight = $this->resolveAuthorizationHandoff($token);
        if ($flight === null) {
            throw ValidationException::withMessages(['payload' => 'This PIC authorization handoff is invalid, expired, or no longer current.']);
        }

        $this->guardPicAccess($flight, $user);
        $this->eligibleCredentials($user, $flight);

        $declinedFlight = DB::transaction(function () use ($flight, $user, $reason, $token, $evidence): Flight {
            $flight = Flight::query()->lockForUpdate()->findOrFail($flight->getKey());
            $user = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $this->guardPicAccess($flight, $user);
            $this->eligibleCredentials($user, $flight);
            $this->guardCurrentAuthorizationState($flight, $user);
            $this->guardHandoffToken($flight, $token);
            $packages = app(FilingAuthorizationService::class);
            $package = $packages->assertReviewedPackageCurrent($flight);

            $flight->forceFill([
                'pic_authorization_status' => 'declined',
                'pic_authorization_declined_by_user_id' => $user->getKey(),
                'pic_authorization_declined_at' => now(),
                'pic_authorization_decline_reason' => $reason,
                'pic_authorization_token' => null,
                'pic_authorization_token_expires_at' => null,
            ])->save();

            $packages->decide($package, $flight, $user, false, $evidence, null, $reason);

            FlightPlanEvent::record($flight, FlightPlanEvent::TYPE_PIC_DECLINED, $user, null, [
                'revision_number' => $flight->revision_number,
                'authorization_method' => 'QR',
                'filing_authorization_id' => $package->getKey(),
                ...($evidence === null ? [] : ['authentication' => $evidence->auditValues()]),
            ], $reason);

            return $flight->refresh();
        });

        return $declinedFlight;
    }

    /** @return array{pilot_name: ?string, license: ?string, ratings: ?string, license_expiry_date: ?string} */
    public function eligibleCredentials(User $user, Flight $flight, bool $lockForUpdate = false): array
    {
        $this->guardEligiblePic($user);

        $credentials = PilotFlightPlanCredentials::forUser($user, $flight->date_of_flight, $lockForUpdate);

        if (! $credentials['license_valid'] || blank($credentials['license']) || blank($credentials['pilot_name'])) {
            throw ValidationException::withMessages(['payload' => 'Your verified pilot profile is incomplete or expired for this flight.']);
        }

        return $credentials;
    }

    public function guardEligiblePic(User $user): void
    {
        if (! $user->is_active || ! $user->isPilot()) {
            throw ValidationException::withMessages(['payload' => 'Only an active eligible pilot may authorize or decline as PIC.']);
        }
        $profile = $user->pilotProfile;

        if ($profile === null || ! in_array($profile->license_type, self::ELIGIBLE_LICENSES, true)) {
            throw ValidationException::withMessages(['payload' => 'Only verified PPL, CPL, or ATPL holders may authorize as PIC.']);
        }
    }

    public function resolveAccessibleFlightFromPayload(string $payload, User $user, bool $requirePendingAuthorization = true): Flight
    {
        $payloadService = app(FlightPlanQrPayloadService::class);
        $parsed = $payloadService->parsePayload($payload);

        if (($parsed['format'] ?? null) !== 'v2-offline' || ! is_array($parsed['snapshot'] ?? null)) {
            throw ValidationException::withMessages(['payload' => 'A current signed Echo flight-plan QR is required for PIC authorization.']);
        }

        $flight = Flight::query()->find((int) $parsed['flight_id']);

        if ($flight === null) {
            throw ValidationException::withMessages(['payload' => 'The signed QR does not identify an accessible saved flight plan.']);
        }

        if (! FlightAccess::canAccessPicAuthorization($user, $flight)) {
            $message = ($user->isPilot() || $user->isDispatch())
                && ! FlightAccess::operatorMatches($user, $flight)
                ? "This flight plan belongs to another operator. PIC authorization is limited to eligible pilots associated with the flight's operator."
                : 'The signed QR does not identify an accessible saved flight plan.';

            throw ValidationException::withMessages(['payload' => $message]);
        }

        if (! $payloadService->snapshotMatchesFlight($parsed['snapshot'], $flight)) {
            throw ValidationException::withMessages(['payload' => 'This QR payload is stale. Scan the current flight-plan QR again.']);
        }

        if ($requirePendingAuthorization && ! $this->needsAuthorization($flight)) {
            throw ValidationException::withMessages(['payload' => 'This flight plan no longer requires PIC authorization.']);
        }

        return $flight;
    }

    private function guardCurrentAuthorizationState(Flight $flight, User $user, bool $enforcePreparerRestriction = true): void
    {
        abort_unless(! $flight->hasBeenAccepted() && ! $flight->isAvsecFrozen() && ! $flight->isPicAuthorizationDeclined()
            && in_array($flight->status, [FlightPlanStatus::AwaitingPic, FlightPlanStatus::Pending], true), 403);
        if (! $this->needsAuthorization($flight)) {
            throw ValidationException::withMessages(['payload' => 'This flight plan no longer requires PIC authorization.']);
        }

        if ($enforcePreparerRestriction && (int) $flight->prepared_by_user_id === (int) $user->getKey()) {
            throw ValidationException::withMessages(['payload' => 'The preparer cannot authorize their own flight-plan submission.']);
        }
    }

    private function guardHandoffToken(Flight $flight, string $token): void
    {
        $current = $this->resolveAuthorizationHandoff($token);

        if ($current === null || (int) $current->getKey() !== (int) $flight->getKey()) {
            throw ValidationException::withMessages(['payload' => 'This PIC authorization handoff is invalid, expired, or no longer current.']);
        }
    }

    /** @param array{pilot_name: ?string, license: ?string, ratings: ?string, license_expiry_date: ?string} $credentials */
    private function assertManifestForPic(Flight $flight, User $pic, array $credentials): void
    {
        $candidate = clone $flight;
        $candidate->forceFill([
            'pilot_in_command_user_id' => $pic->id, 'pilot_in_command' => $credentials['pilot_name'],
            'pilot_license_no' => $credentials['license'], 'pilot_ratings' => $credentials['ratings'],
            'license_expiry_date' => $credentials['license_expiry_date'],
        ]);
        $candidate->setRelation('manifest', $flight->manifest()->with('persons')->first());
        app(FlightManifestService::class)->assertComplete($candidate, loaded: true);
    }

    private function guardPicAccess(Flight $flight, User $user): void
    {
        if (! FlightAccess::canAccessPicAuthorization($user, $flight)) {
            throw ValidationException::withMessages(['payload' => 'You are not authorized to act on this flight plan.']);
        }
    }
}
