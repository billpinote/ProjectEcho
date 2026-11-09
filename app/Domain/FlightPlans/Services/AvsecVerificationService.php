<?php

namespace App\Domain\FlightPlans\Services;

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
    public const CONFIRMATION = 'I confirm that the required PNP AVSEC clearance procedures have been completed for this flight.';

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
