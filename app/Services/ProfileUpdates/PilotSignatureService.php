<?php

namespace App\Services\ProfileUpdates;

use App\Domain\Users\Enums\UserRole;
use App\Models\PilotSignature;
use App\Models\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PilotSignatureService
{
    public function __construct(private readonly ProfileAuditRecorder $auditRecorder) {}

    /** @param array{bytes: string, width: int, height: int, sha256: string, source: string} $image */
    public function stage(ProfileUpdateRequest $request, array $image): PilotSignature
    {
        $owner = $request->user;
        abort_unless($owner->is_active && $owner->isPilot(), 403);
        if ($owner->signatureVersions()->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages(['data.signature_submission' => 'A signature is already pending review. Wait for its review before submitting another.']);
        }
        $current = $owner->approvedSignature()->first();
        if ($current !== null && hash_equals($current->sha256, $image['sha256'])) {
            throw ValidationException::withMessages(['data.signature_submission' => 'This signature is already approved.']);
        }
        $path = 'pilot-signatures/'.$owner->getKey().'/'.Str::uuid().'.png';
        if (! Storage::disk('local')->put($path, $image['bytes'], ['visibility' => 'private'])) {
            throw new \RuntimeException('The signature could not be stored. Please try again.');
        }
        try {
            $signature = new PilotSignature;
            $signature->forceFill([
                'user_id' => $owner->getKey(),
                'profile_update_request_id' => $request->getKey(),
                'version' => ((int) $owner->signatureVersions()->max('version')) + 1,
                'status' => 'pending',
                'stored_path' => $path,
                'sha256' => $image['sha256'],
                'source' => $image['source'],
                'width' => $image['width'],
                'height' => $image['height'],
                'file_size' => strlen($image['bytes']),
                'submitted_at' => now(),
            ])->save();
            $this->auditRecorder->recordFieldChange($owner, $owner, 'signature_submitted', 'profile_update_request',
                'pilot_signature', $current?->reference(), $signature->reference(), $signature, $request);

            return $signature;
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }
    }

    public function review(ProfileUpdateRequest $request, User $reviewer, bool $approve, ?string $remarks): void
    {
        abort_unless($reviewer->is_active && in_array($reviewer->role, [UserRole::Admin, UserRole::Artisan], true), 403);
        abort_unless($request->isPending(), 409);
        $signature = $request->signature()->lockForUpdate()->firstOrFail();
        abort_unless($signature->user_id === $request->user_id
            && $signature->getKey() === ($request->requested_changes['pilot_signature']['new'] ?? null)
            && hash_equals($signature->sha256, (string) ($request->requested_changes['pilot_signature']['sha256'] ?? '')), 409, 'The signature version does not match this review.');
        abort_unless($signature->status === 'pending', 409, 'This signature has already been reviewed.');
        $owner = User::query()->lockForUpdate()->findOrFail($signature->user_id);
        $current = $owner->approvedSignature()->first();
        if ($approve) {
            $this->verifiedBytes($signature);
            if ($current !== null) {
                $current->forceFill(['active_user_id' => null, 'status' => 'superseded'])->save();
            }
        }
        $signature->forceFill([
            'status' => $approve ? 'approved' : 'rejected',
            'active_user_id' => $approve ? $owner->getKey() : null,
            'reviewed_by_user_id' => $reviewer->getKey(),
            'reviewed_at' => now(),
        ])->save();
        $this->auditRecorder->recordFieldChange($owner, $reviewer, $approve ? 'signature_approved' : 'signature_rejected',
            'profile_update_request', 'pilot_signature', $current?->reference(), $signature->reference(), $signature, $request, $remarks);
        if ($approve && $current !== null) {
            $this->auditRecorder->recordFieldChange($owner, $reviewer, 'signature_replaced', 'profile_update_request',
                'pilot_signature', $current->reference(), $signature->reference(), $signature, $request, $remarks);
        }
    }

    public function verifiedBytes(PilotSignature $signature): string
    {
        abort_unless(Storage::disk('local')->exists($signature->stored_path), 404);
        $bytes = Storage::disk('local')->get($signature->stored_path);
        abort_unless(hash_equals($signature->sha256, hash('sha256', $bytes)), 409, 'The stored signature failed its integrity check.');

        return $bytes;
    }
}
