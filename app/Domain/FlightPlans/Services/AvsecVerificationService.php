<?php

namespace App\Domain\FlightPlans\Services;

use App\Domain\FlightPlans\Enums\AvsecClearanceState;
use App\Domain\FlightPlans\Enums\FlightPlanStatus;
use App\Domain\FlightPlans\Support\FilingDocumentRegistry;
use App\Domain\Users\Enums\UserRole;
use App\Models\AvsecVerification;
use App\Models\Flight;
use App\Models\FlightPlanEvent;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\ConcurrencyErrorDetector;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AvsecVerificationService
{
    public const CONFIRMATION = 'Confirm that PNP-AVSEC clearance has been granted on the signed offline clearance form.';

    public function __construct(private FlightPlanQrPayloadService $payloads, private FilingAuthorizationService $authorizations, private FilingDocumentRegistry $documents) {}

    public static function eligibleOfficer(?User $user): bool
    {
        return $user !== null && $user->is_active && $user->role === UserRole::Avsec
            && strtoupper(trim((string) $user->station)) === 'RPUS';
    }

    public static function requiredFor(Flight $flight): bool
    {
        return strtoupper(trim((string) $flight->departure_aerodrome)) === 'RPUS';
    }

    public function resolve(string $payload, User $actor): Flight
    {
        if (! self::eligibleOfficer($actor->fresh())) {
            throw new AuthorizationException;
        }
        $parsed = $this->payloads->parsePayload($payload);
        if ($parsed === null || ($parsed['format'] ?? null) !== 'v2-offline') {
            throw ValidationException::withMessages(['payload' => 'Scan a valid signed current flight-plan QR.']);
        }
        $flight = Flight::query()->lockForUpdate()->find($parsed['flight_id']);
        if ($flight === null) {
            throw ValidationException::withMessages(['payload' => 'A saved flight record is required; an offline snapshot cannot be confirmed.']);
        }
        if (! self::requiredFor($flight) || ! $actor->can('view', $flight)) {
            throw new AuthorizationException;
        }
        if ((int) ($parsed['snapshot']['revision_number'] ?? 0) !== (int) $flight->revision_number
            || ! $this->payloads->snapshotMatchesFlight($parsed['snapshot'], $flight)) {
            throw ValidationException::withMessages(['payload' => 'The QR is stale. Scan the current flight-plan revision.']);
        }
        if (! $flight->isPicAuthorizationCurrent()) {
            throw ValidationException::withMessages(['payload' => 'PIC authorization must be completed for the current flight plan before AVSEC review.']);
        }

        return $flight;
    }

    public function confirm(string $payload, User $actor, bool $confirmed, ?string $reference = null, ?string $reviewedFingerprint = null): AvsecVerification
    {
        Validator::make(['confirmation' => $confirmed, 'reference' => $reference], [
            'confirmation' => ['accepted'], 'reference' => ['nullable', 'string', 'max:255'],
        ])->validate();

        try {
            return DB::transaction(function () use ($payload, $actor, $reference, $reviewedFingerprint): AvsecVerification {
                $flight = $this->resolve($payload, $actor);
                if ($flight->avsecVerification()->exists()) {
                    throw ValidationException::withMessages(['avsec' => 'This flight already has a permanent AVSEC confirmation.']);
                }
                $this->assertNoDenial($flight);
                if ($flight->status !== FlightPlanStatus::Pending || $flight->hasBeenAccepted() || $flight->isPendingExpired()
                    || ! $flight->isPicAuthorizationCurrent() || ! $this->authorizations->isReadyForAtmo($flight)) {
                    throw ValidationException::withMessages(['avsec' => 'A current PIC-authorized flight plan and complete, signed manifest are required before AVSEC confirmation.']);
                }
                if ($flight->pic_authorization_archived_at !== null) {
                    throw ValidationException::withMessages(['avsec' => 'Archived filings cannot be confirmed.']);
                }
                if ($reviewedFingerprint !== null && ! hash_equals($reviewedFingerprint, $this->reviewFingerprint($flight))) {
                    throw ValidationException::withMessages(['avsec' => 'The reviewed documents have changed. Reload and review the current flight plan and manifest.']);
                }
                app(FlightManifestService::class)->assertComplete($flight);
                $pdfs = app(FlightPlanPdfService::class);
                if ($pdfs->storedPaths($flight)->isEmpty()) {
                    $pdfs->regenerate($flight);
                }
                // Preserve the manifest PDF before the permanent lock, rather than rewriting it at acceptance.
                app(ManifestPdfService::class)->snapshot($flight);
                $content = $this->approvedContent($flight);
                $verification = new AvsecVerification;
                $verification->forceFill([
                    'flight_id' => $flight->id, 'verifier_user_id' => $actor->id, 'station' => 'RPUS',
                    'verifier_name' => $actor->fullName(), 'verified_at' => now('UTC'),
                    'flight_revision' => $flight->revision_number, 'content_sha256' => FilingDocumentRegistry::fingerprint($content),
                    'approved_content' => $content, 'pnp_reference_number' => filled($reference) ? trim($reference) : null,
                ])->save();
                FlightPlanEvent::record($flight, FlightPlanEvent::TYPE_AVSEC_VERIFIED, $actor, null, [
                    'decision' => 'granted',
                    'verification_id' => $verification->id, 'flight_revision' => $verification->flight_revision,
                    'content_sha256' => $verification->content_sha256, 'confirmation' => self::CONFIRMATION,
                    'pnp_reference_number' => $verification->pnp_reference_number, 'station' => 'RPUS',
                ]);

                return $verification;
            }, 3);
        } catch (DeadlockException|QueryException $exception) {
            if (! (new ConcurrencyErrorDetector)->causedByConcurrencyError($exception)) {
                throw $exception;
            }

            throw ValidationException::withMessages(['avsec' => 'The filing is being changed or confirmed by another request. Reload the current record before retrying.']);
        }
    }

    public function reviewFingerprint(Flight $flight): string
    {
        return FilingDocumentRegistry::fingerprint($this->approvedContent($flight));
    }

    public function manifestAvailable(Flight $flight): bool
    {
        $flight->loadMissing('manifest.persons');
        try {
            app(FlightManifestService::class)->assertComplete($flight, loaded: true);

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    public function denialFor(Flight $flight, bool $loaded = false): ?FlightPlanEvent
    {
        $denials = $loaded ? $flight->avsecDenials : $flight->avsecDenials()->get();

        return $denials->sortByDesc('id')->first(
            fn (FlightPlanEvent $event) => (int) ($event->new_values['flight_revision'] ?? 0) === (int) $flight->revision_number,
        );
    }

    public function currentState(Flight $flight, bool $loaded = false): AvsecClearanceState
    {
        if (! $flight->exists || ! self::requiredFor($flight)) {
            return AvsecClearanceState::Awaiting;
        }
        if ($this->validFor($flight, $loaded)) {
            return AvsecClearanceState::Granted;
        }

        return $this->denialFor($flight, $loaded) !== null ? AvsecClearanceState::Denied : AvsecClearanceState::Awaiting;
    }

    public function canDecide(Flight $flight, ?User $actor): bool
    {
        return self::eligibleOfficer($actor) && self::requiredFor($flight) && $actor->can('view', $flight)
            && $flight->status === FlightPlanStatus::Pending && ! $flight->hasBeenAccepted()
            && ! $flight->isPendingExpired() && $flight->pic_authorization_archived_at === null
            && $flight->avsecVerification === null && $this->denialFor($flight) === null
            && $this->manifestAvailable($flight) && $flight->isPicAuthorizationCurrent()
            && $this->authorizations->isReadyForAtmo($flight);
    }

    public function deny(string $payload, User $actor, string $reason, ?string $reference = null, ?string $reviewedFingerprint = null): FlightPlanEvent
    {
        $reason = trim($reason);
        Validator::make(['reason' => $reason, 'reference' => $reference], [
            'reason' => ['required', 'string', 'max:255'], 'reference' => ['nullable', 'string', 'max:255'],
        ])->validate();

        try {
            return DB::transaction(function () use ($payload, $actor, $reason, $reference, $reviewedFingerprint): FlightPlanEvent {
                $flight = $this->resolve($payload, $actor);
                if (! $this->canDecide($flight, $actor)) {
                    throw ValidationException::withMessages(['avsec' => 'A current Pilot-authorized flight plan and complete, signed manifest with no recorded clearance decision are required.']);
                }
                $fingerprint = $this->reviewFingerprint($flight);
                if ($reviewedFingerprint !== null && ! hash_equals($reviewedFingerprint, $fingerprint)) {
                    throw ValidationException::withMessages(['avsec' => 'The reviewed documents have changed. Reload and review the current flight plan and manifest.']);
                }

                return FlightPlanEvent::record($flight, FlightPlanEvent::TYPE_AVSEC_DENIED, $actor, null, [
                    'decision' => 'denied', 'flight_revision' => $flight->revision_number,
                    'content_sha256' => $fingerprint, 'verifier_name' => $actor->fullName(),
                    'station' => 'RPUS', 'pnp_reference_number' => filled($reference) ? trim($reference) : null,
                ], $reason);
            }, 3);
        } catch (DeadlockException|QueryException $exception) {
            if (! (new ConcurrencyErrorDetector)->causedByConcurrencyError($exception)) {
                throw $exception;
            }

            throw ValidationException::withMessages(['avsec' => 'Another request is changing this filing. Reload before retrying.']);
        }
    }

    private function assertNoDenial(Flight $flight): void
    {
        if ($this->denialFor($flight) !== null) {
            throw ValidationException::withMessages(['avsec' => 'Clearance has already been denied for this flight revision.']);
        }
    }

    public function validFor(Flight $flight, bool $loaded = false): bool
    {
        $verification = $loaded ? $flight->avsecVerification : $flight->avsecVerification()->first();

        return $verification !== null && $verification->station === 'RPUS'
            && $verification->flight_revision === (int) $flight->revision_number
            && hash_equals($verification->content_sha256, FilingDocumentRegistry::fingerprint($this->approvedContent($flight, $loaded)));
    }

    private function approvedContent(Flight $flight, bool $loaded = false): array
    {
        $evidence = [];
        foreach (['flight_plan', 'passenger_cargo_manifest'] as $type) {
            $package = $this->authorizations->currentForDocument($flight, $type, loaded: $loaded);
            $evidence[$type] = $package === null ? null : [
                'authorization_id' => $package->id, 'signature_sha256' => $package->signature_sha256,
                'pdf_sha256' => $package->pdf_documents[$type]['sha256'] ?? null,
            ];
        }

        $pdfs = app(FlightPlanPdfService::class);
        $attachments = [];
        foreach ($pdfs->storedPaths($flight, $loaded) as $path) {
            $attachments[$path] = hash('sha256', Storage::disk($pdfs->diskFor($path))->get($path));
        }

        return ['documents' => $this->documents->snapshots($flight, ! $loaded), 'evidence' => $evidence, 'attachments' => $attachments];
    }
}
