<?php

namespace App\Domain\FlightPlans\Services;

use App\Auth\AuthenticationEvidence;
use App\Domain\FlightPlans\Enums\FlightPlanStatus;
use App\Domain\FlightPlans\Support\FilingDocumentRegistry;
use App\Models\FilingAuthorization;
use App\Models\Flight;
use App\Models\FlightPlanEvent;
use App\Models\PilotSignature;
use App\Models\User;
use App\Services\ProfileUpdates\PilotSignatureService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class FilingAuthorizationService
{
    /** @var array<int, bool> */
    private array $mutating = [];

    public function __construct(private FilingDocumentRegistry $documents, private PilotSignatureService $signatures) {}

    public function request(Flight $flight, ?array $signedDocumentTypes = null): FilingAuthorization
    {
        $flight->assertAvsecEditable();
        $documents = $this->documents->snapshots($flight);
        $package = new FilingAuthorization;
        $package->forceFill([
            'flight_id' => $flight->getKey(), 'flight_revision' => (int) ($flight->revision_number ?? 1),
            'status' => 'pending', 'documents' => $documents,
            'fingerprint' => FilingDocumentRegistry::fingerprint($documents), 'requested_at' => now(),
            'signed_document_types' => $signedDocumentTypes,
        ])->save();

        return $package;
    }

    public function latestFor(Flight $flight): ?FilingAuthorization
    {
        return $flight->filingAuthorizations()->latest('id')->first();
    }

    public function assertReviewedPackageCurrent(Flight $flight): FilingAuthorization
    {
        $package = $this->latestFor($flight);
        if ($package === null || $package->status !== 'pending'
            || ! hash_equals($package->fingerprint, FilingDocumentRegistry::fingerprint($this->documents->snapshots($flight)))) {
            throw ValidationException::withMessages(['payload' => 'The filing documents changed. Scan the current QR and review the complete filing again.']);
        }

        return $package;
    }

    public function decide(FilingAuthorization $package, Flight $flight, User $pic, bool $approve, ?AuthenticationEvidence $evidence, ?PilotSignature $signature, ?string $reason = null): void
    {
        $authorized = $approve ? $this->documents->snapshots($flight) : null;
        if ($approve && ! $this->documents->matchesReviewedContent($package->documents, $authorized)) {
            throw ValidationException::withMessages(['payload' => 'The filing document set changed. Review the complete filing again.']);
        }
        $pdfs = [];
        if ($approve) {
            $bytes = $signature === null ? null : $this->signatures->verifiedBytes($signature);
            foreach ($package->signed_document_types ?? array_keys($authorized) as $type) {
                $pdf = $this->documents->render($type, $authorized, $bytes);
                $pdfs[$type] = ['bytes' => base64_encode($pdf), 'sha256' => hash('sha256', $pdf)];
            }
        }
        $package->forceFill([
            'status' => $approve ? 'authorized' : 'declined', 'pic_user_id' => $pic->getKey(),
            'decided_at' => now(), 'authentication' => $evidence?->auditValues(), 'reason' => $reason,
            'pilot_signature_id' => $signature?->getKey(), 'signature_version' => $signature?->version,
            'signature_sha256' => $signature?->sha256, 'authorized_documents' => $authorized,
            'authorized_fingerprint' => $authorized === null ? null : FilingDocumentRegistry::fingerprint($authorized),
            'pdf_documents' => $pdfs,
        ])->save();
    }

    public function approvedSignature(User $pic): PilotSignature
    {
        User::query()->lockForUpdate()->findOrFail($pic->getKey());
        $signature = $pic->approvedSignature()->lockForUpdate()->first();
        if ($signature === null || $signature->status !== 'approved' || (int) $signature->user_id !== (int) $pic->getKey()) {
            throw ValidationException::withMessages(['signature' => 'No approved signature is available. Submit a signature for verification, or authorize this filing without an electronic signature.']);
        }
        $this->signatures->verifiedBytes($signature);

        return $signature;
    }

    public function isCurrent(Flight $flight, FilingAuthorization $package): bool
    {
        $flight = $flight->fresh() ?? $flight;

        return $package->status === 'authorized'
            && hash_equals((string) $package->authorized_fingerprint, FilingDocumentRegistry::fingerprint($this->documents->snapshots($flight)));
    }

    public function signatureBytes(Flight $flight, string $type = 'flight_plan'): ?string
    {
        $package = $this->currentForDocument($flight, $type);

        return $package === null ? null : $this->historicalSignatureBytes($package);
    }

    public function currentForDocument(Flight $flight, string $type, bool $requireSignature = true, bool $loaded = false): ?FilingAuthorization
    {
        if (! $flight->exists || $flight->isPicAuthorizationDeclined()) {
            return null;
        }
        $flight = $loaded ? $flight : $this->currentState($flight);
        if ($flight === null || $flight->isPicAuthorizationDeclined()) {
            return null;
        }
        $documents = $this->documents->snapshots($flight, false);
        if (! isset($documents[$type])) {
            return null;
        }
        foreach ($flight->filingAuthorizations->sortByDesc('id') as $package) {
            $types = $package->signed_document_types ?? array_keys($package->authorized_documents ?? []);
            if ($package->status !== 'authorized' || ! in_array($type, $types, true)
                || ! isset($package->authorized_documents[$type])
                || (int) $package->pic_user_id !== (int) $flight->pilot_in_command_user_id
                || ! hash_equals(FilingDocumentRegistry::documentFingerprint($documents, $type),
                    FilingDocumentRegistry::documentFingerprint($package->authorized_documents, $type))) {
                continue;
            }
            if ($requireSignature) {
                if ($package->pilot_signature_id === null) {
                    return null;
                }
                try {
                    $this->historicalSignatureBytes($package);
                } catch (HttpException) {
                    return null;
                }
            }

            return $package;
        }

        return null;
    }

    public function isReadyForAtmo(Flight $flight, bool $loaded = false): bool
    {
        $flight = $loaded ? $flight : $this->currentState($flight);
        if ($flight === null || $flight->manifest === null || $flight->isPicAuthorizationDeclined()) {
            return false;
        }

        return $this->currentForDocument($flight, 'flight_plan', loaded: true) !== null
            && $this->currentForDocument($flight, 'passenger_cargo_manifest', loaded: true) !== null;
    }

    public function readyIds(Builder $query): array
    {
        return (clone $query)->whereHas('filingAuthorizations', fn (Builder $query) => $query->where('status', 'authorized')->whereNotNull('pilot_signature_id'))
            ->with(['manifest.persons', 'filingAuthorizations' => fn ($query) => $query->select([
                'id', 'flight_id', 'status', 'pic_user_id', 'pilot_signature_id', 'signature_version', 'signature_sha256',
                'signed_document_types', 'authorized_documents',
            ]), 'filingAuthorizations.pilotSignature'])->get()
            ->filter(fn (Flight $flight): bool => $this->isReadyForAtmo($flight, true))->modelKeys();
    }

    private function currentState(Flight $flight): ?Flight
    {
        return Flight::query()->whereKey($flight->id)->lockForUpdate()->with([
            'manifest' => fn ($query) => $query->lockForUpdate(),
            'manifest.persons' => fn ($query) => $query->lockForUpdate(),
            'filingAuthorizations' => fn ($query) => $query->lockForUpdate(),
            'filingAuthorizations.pilotSignature' => fn ($query) => $query->lockForUpdate(),
        ])->first();
    }

    public function historicalSignatureBytes(FilingAuthorization $package): ?string
    {
        if ($package->status !== 'authorized' || $package->pilot_signature_id === null) {
            return null;
        }
        $signature = $package->pilotSignature;
        abort_unless($signature !== null && (int) $signature->user_id === (int) $package->pic_user_id
            && $signature->version === $package->signature_version
            && hash_equals($signature->sha256, $package->signature_sha256), 409, 'Signature evidence integrity check failed.');

        return $this->signatures->verifiedBytes($signature);
    }

    public function mutate(Flight $flight, \Closure $mutation): mixed
    {
        $id = (int) $flight->getKey();
        if (isset($this->mutating[$id])) {
            return $mutation();
        }

        return DB::transaction(function () use ($flight, $id, $mutation): mixed {
            $locked = Flight::query()->lockForUpdate()->findOrFail($id);
            $this->mutating[$id] = true;
            try {
                $hasHistory = $locked->filingAuthorizations()->exists();
                $before = $hasHistory ? $this->documents->snapshots($locked) : null;
                $result = $mutation();
                $locked->refresh();
                $after = $hasHistory ? $this->documents->snapshots($locked) : null;
                if ($hasHistory && ! hash_equals(FilingDocumentRegistry::fingerprint($before), FilingDocumentRegistry::fingerprint($after))) {
                    $flightChanged = FilingDocumentRegistry::documentFingerprint($before, 'flight_plan') !== FilingDocumentRegistry::documentFingerprint($after, 'flight_plan');
                    $manifestChanged = FilingDocumentRegistry::documentFingerprint($before, 'passenger_cargo_manifest') !== FilingDocumentRegistry::documentFingerprint($after, 'passenger_cargo_manifest');
                    $locked->forceFill([
                        'revision_number' => max(1, (int) $locked->revision_number) + 1,
                        'flight_document_revision' => max(1, (int) $locked->flight_document_revision) + ($flightChanged ? 1 : 0),
                        'pic_authorization_token' => null, 'pic_authorization_token_expires_at' => null,
                        'status' => FlightPlanStatus::AwaitingPic, 'reviewed_at' => null,
                    ]);
                    if ($flightChanged) {
                        $locked->forceFill(['pic_authorized_by_user_id' => null, 'pic_authorized_at' => null,
                            'pic_authorized_revision' => null, 'pic_authorization_method' => null, 'pic_authorization_status' => null]);
                    }
                    if ($manifestChanged && ($manifest = $locked->manifest()->first()) !== null) {
                        $manifest->forceFill(['document_revision' => max(1, (int) $manifest->document_revision) + 1])->saveQuietly();
                    }
                    $locked->saveQuietly();
                    FlightPlanEvent::record($locked, 'filing_authorization_invalidated', auth()->user(), null, [
                        'revision_number' => $locked->revision_number,
                        'flight_plan_changed' => $flightChanged, 'manifest_changed' => $manifestChanged,
                    ]);
                    $flight->setRawAttributes($locked->getAttributes(), true);
                }

                return $result;
            } finally {
                unset($this->mutating[$id]);
            }
        });
    }

    /** Decisions change PIC credentials deliberately, after validating the reviewed snapshot. */
    public function recordingDecision(Flight $flight, \Closure $decision): mixed
    {
        $id = (int) $flight->getKey();
        $this->mutating[$id] = true;
        try {
            return $decision();
        } finally {
            unset($this->mutating[$id]);
        }
    }
}
