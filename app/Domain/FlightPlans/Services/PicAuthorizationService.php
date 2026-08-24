<?php

namespace App\Domain\FlightPlans\Services;

use App\Domain\FlightPlans\Enums\FlightPlanStatus;
use App\Domain\FlightPlans\Support\FlightAccess;
use App\Domain\FlightPlans\Support\PilotFlightPlanCredentials;
use App\Domain\Pilots\Enums\PilotLicenseType;
use App\Models\Flight;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PicAuthorizationService
{
    private const HANDOFF_TTL_MINUTES = 30;

    private const ELIGIBLE_LICENSES = [
        PilotLicenseType::PrivatePilot,
        PilotLicenseType::CommercialPilot,
        PilotLicenseType::AirlineTransportPilot,
    ];

    public function createAuthorizationHandoff(Flight $flight): string
    {
        abort_unless($flight->requiresPicAuthorization()
            && ! $flight->isPicAuthorizationCurrent()
            && ! $flight->isPicAuthorizationDeclined(), 422);

        $token = Str::random(64);
        $revision = (int) ($flight->revision_number ?? 1);
        $flight->forceFill([
            'pic_authorization_token' => hash('sha256', $token.'.'.$revision),
            'pic_authorization_token_expires_at' => now()->addMinutes(self::HANDOFF_TTL_MINUTES),
        ])->saveQuietly();

        return $token;
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
            || ! $flight->requiresPicAuthorization()
            || $flight->isPicAuthorizationCurrent()
        ) {
            return null;
        }

        return $flight;
    }

    public function authorizeFromPayload(string $payload, User $authorizer): Flight
    {
        $flight = $this->resolveAccessibleFlightFromPayload($payload, $authorizer);
        $token = $this->createAuthorizationHandoff($flight);

        return $this->authorizeFromHandoff($token, $authorizer);
    }

    public function authorizeFromHandoff(string $token, User $authorizer): Flight
    {
        $flight = $this->resolveAuthorizationHandoff($token);
        if ($flight === null) {
            throw ValidationException::withMessages(['payload' => 'This PIC authorization handoff is invalid, expired, or no longer current.']);
        }

        $this->guardPicAccess($flight, $authorizer);
        $credentials = $this->eligibleCredentials($authorizer, $flight);

        $authorizedFlight = DB::transaction(function () use ($flight, $authorizer, $credentials, $token): Flight {
            $flight = Flight::query()->lockForUpdate()->findOrFail($flight->getKey());
            $this->guardCurrentAuthorizationState($flight, $authorizer);
            $this->guardHandoffToken($flight, $token);

            $flight->forceFill([
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
            ])->save();

            return $flight->refresh();
        });

        app(FlightPlanPdfService::class)->regenerate($authorizedFlight);

        return $authorizedFlight;
    }

    public function declineFromPayload(string $payload, User $user, ?string $reason = null): Flight
    {
        $flight = $this->resolveAccessibleFlightFromPayload($payload, $user);
        $token = $this->createAuthorizationHandoff($flight);

        return $this->declineFromHandoff($token, $user, $reason);
    }

    public function declineFromHandoff(string $token, User $user, ?string $reason = null): Flight
    {
        $reason = filled($reason) ? trim($reason) : null;
        if ($reason === null || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['declineReason' => 'A reason for declining is required and must not exceed 500 characters.']);
        }
        $flight = $this->resolveAuthorizationHandoff($token);
        if ($flight === null) {
            throw ValidationException::withMessages(['payload' => 'This PIC authorization handoff is invalid, expired, or no longer current.']);
        }

        $this->guardPicAccess($flight, $user);

        return DB::transaction(function () use ($flight, $user, $reason, $token): Flight {
            $flight = Flight::query()->lockForUpdate()->findOrFail($flight->getKey());
            $this->guardCurrentAuthorizationState($flight, $user);
            $this->guardHandoffToken($flight, $token);

            $flight->forceFill([
                'pic_authorization_status' => 'declined',
                'pic_authorization_declined_by_user_id' => $user->getKey(),
                'pic_authorization_declined_at' => now(),
                'pic_authorization_decline_reason' => $reason,
                'pic_authorization_token' => null,
                'pic_authorization_token_expires_at' => null,
            ])->save();

            return $flight->refresh();
        });
    }

    /** @return array{pilot_name: ?string, license: ?string, ratings: ?string, license_expiry_date: ?string} */
    public function eligibleCredentials(User $user, Flight $flight): array
    {
        $profile = $user->pilotProfile;

        if ($profile === null || ! in_array($profile->license_type, self::ELIGIBLE_LICENSES, true)) {
            throw ValidationException::withMessages(['payload' => 'Only verified PPL, CPL, or ATPL holders may authorize as PIC.']);
        }

        $credentials = PilotFlightPlanCredentials::forUser($user, $flight->date_of_flight);

        if (! $credentials['license_valid'] || blank($credentials['license']) || blank($credentials['pilot_name'])) {
            throw ValidationException::withMessages(['payload' => 'Your verified pilot profile is incomplete or expired for this flight.']);
        }

        return $credentials;
    }

    public function resolveAccessibleFlightFromPayload(string $payload, User $user): Flight
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

        if (! $flight->requiresPicAuthorization() || $flight->isPicAuthorizationCurrent()) {
            throw ValidationException::withMessages(['payload' => 'This flight plan no longer requires PIC authorization.']);
        }

        return $flight;
    }

    private function guardCurrentAuthorizationState(Flight $flight, User $user, bool $enforcePreparerRestriction = true): void
    {
        if (! $flight->requiresPicAuthorization() || $flight->isPicAuthorizationCurrent()) {
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

    private function guardPicAccess(Flight $flight, User $user): void
    {
        if (! FlightAccess::canAccessPicAuthorization($user, $flight)) {
            throw ValidationException::withMessages(['payload' => 'You are not authorized to act on this flight plan.']);
        }
    }
}
