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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FilingAuthorizationService
{
    /** @var array<int, bool> */
    private array $mutating = [];

    public function __construct(private FilingDocumentRegistry $documents, private PilotSignatureService $signatures) {}

    public function request(Flight $flight): FilingAuthorization
    {
        $documents = $this->documents->snapshots($flight);
        $package = new FilingAuthorization;
        $package->forceFill([
            'flight_id' => $flight->getKey(), 'flight_revision' => (int) ($flight->revision_number ?? 1),
            'status' => 'pending', 'documents' => $documents,
            'fingerprint' => FilingDocumentRegistry::fingerprint($documents), 'requested_at' => now(),
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
            foreach (array_keys($authorized) as $type) {
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
        $signature = $pic->approvedSignature()->first();
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

    public function signatureBytes(Flight $flight): ?string
    {
        if (! $flight->exists || $flight->isPicAuthorizationDeclined() || ! $flight->isPicAuthorizationCurrent()) {
            return null;
        }
        $package = $this->latestFor($flight);
        if ($package?->pilot_signature_id === null || ! $this->isCurrent($flight, $package)) {
            return null;
        }

        return $this->historicalSignatureBytes($package);
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

    public function documentChanged(Flight $flight): void
    {
        if (! $flight->filingAuthorizations()->exists()) {
            return;
        }
        $flight->forceFill([
            'revision_number' => max(1, (int) $flight->revision_number) + 1,
            'pic_authorized_by_user_id' => null, 'pic_authorized_at' => null,
            'pic_authorized_revision' => null, 'pic_authorization_method' => null,
            'pic_authorization_token' => null, 'pic_authorization_token_expires_at' => null,
            'pic_authorization_status' => null,
            'status' => FlightPlanStatus::AwaitingPic,
        ]);
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
                $before = $hasHistory ? FilingDocumentRegistry::fingerprint($this->documents->snapshots($locked)) : null;
                $result = $mutation();
                $locked->refresh();
                if ($hasHistory && ! hash_equals($before, FilingDocumentRegistry::fingerprint($this->documents->snapshots($locked)))) {
                    $this->documentChanged($locked);
                    $locked->saveQuietly();
                    FlightPlanEvent::record($locked, 'filing_authorization_invalidated', auth()->user(), null, [
                        'revision_number' => $locked->revision_number,
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
