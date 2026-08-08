<?php

namespace App\Services\ProfileUpdates;

use App\Domain\ProfileUpdateRequests\Enums\ProfileUpdateRequestStatus;
use App\Domain\Users\Enums\UserRole;
use App\Models\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProfileUpdateRequestService
{
    private const ACCEPTED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
    ];

    private const MAX_DOCUMENT_SIZE_KB = 5120;

    public function __construct(private readonly ProfileAuditRecorder $auditRecorder) {}

    /**
     * @param  array<string, mixed>  $requestedValues
     * @param  array<int, mixed>  $documents
     */
    public function submit(User $user, array $requestedValues, ?string $reason, array $documents = []): ProfileUpdateRequest
    {
        return DB::transaction(function () use ($user, $requestedValues, $reason, $documents): ProfileUpdateRequest {
            $user->refresh()->loadMissing(['pilotProfile', 'dispatchProfile', 'atcProfile', 'avsecProfile']);
            $changes = ProfileFieldRegistry::changesForRequest($user, $requestedValues);

            if ($changes === []) {
                throw new \InvalidArgumentException('At least one changed profile field is required.');
            }

            $request = $user->profileUpdateRequests()->create([
                'status' => ProfileUpdateRequestStatus::Pending,
                'requested_changes' => $changes,
                'reason' => $this->nullableString($reason),
                'submitted_at' => now(),
            ]);

            foreach ($documents as $document) {
                $this->attachDocument($request, $document);
            }

            return $request;
        });
    }

    public function approve(ProfileUpdateRequest $request, User $reviewer, ?string $remarks = null): ProfileUpdateRequest
    {
        $this->authorizeReviewer($reviewer);

        return DB::transaction(function () use ($request, $reviewer, $remarks): ProfileUpdateRequest {
            /** @var ProfileUpdateRequest $request */
            $request = ProfileUpdateRequest::query()->lockForUpdate()->with('user')->findOrFail($request->getKey());

            if (! $request->isPending()) {
                throw new \RuntimeException('Only pending profile update requests can be approved.');
            }

            $user = $request->user;
            $user->loadMissing(['pilotProfile', 'dispatchProfile', 'atcProfile', 'avsecProfile']);

            foreach ($request->requested_changes ?? [] as $field => $change) {
                if (! array_key_exists($field, ProfileFieldRegistry::fields())) {
                    throw new \InvalidArgumentException("Profile field [{$field}] is not allowed.");
                }

                $old = ProfileFieldRegistry::currentValue($user, $field);
                $new = $change['new'] ?? null;
                $model = ProfileFieldRegistry::apply($user, $field, $new);
                $user->refresh()->loadMissing(['pilotProfile', 'dispatchProfile', 'atcProfile', 'avsecProfile']);

                $this->auditRecorder->recordFieldChange(
                    $user,
                    $reviewer,
                    'profile_change_approved',
                    'profile_update_request',
                    $field,
                    $old,
                    $new,
                    $model,
                    $request,
                    $remarks,
                );
            }

            $request->forceFill([
                'status' => ProfileUpdateRequestStatus::Approved,
                'reviewed_at' => now(),
                'reviewed_by_user_id' => $reviewer->getKey(),
                'reviewer_remarks' => $this->nullableString($remarks),
                'rejection_reason' => null,
            ])->save();

            return $request;
        });
    }

    public function reject(ProfileUpdateRequest $request, User $reviewer, string $reason): ProfileUpdateRequest
    {
        $this->authorizeReviewer($reviewer);

        if (blank($reason)) {
            throw new \InvalidArgumentException('A rejection reason is required.');
        }

        $request->forceFill([
            'status' => ProfileUpdateRequestStatus::Rejected,
            'reviewed_at' => now(),
            'reviewed_by_user_id' => $reviewer->getKey(),
            'rejection_reason' => trim($reason),
            'reviewer_remarks' => trim($reason),
        ])->save();

        return $request;
    }

    private function authorizeReviewer(User $reviewer): void
    {
        abort_unless($reviewer->is_active && in_array($reviewer->role, [UserRole::Admin, UserRole::Artisan], true), 403);
    }

    private function attachDocument(ProfileUpdateRequest $request, mixed $document): void
    {
        if ($document instanceof UploadedFile) {
            $path = $document->store('profile-update-request-documents', 'local');
            $original = $document->getClientOriginalName();
            $mime = $document->getClientMimeType();
            $size = $document->getSize();
        } else {
            $path = $this->normalizePath(is_array($document) ? ($document['stored_path'] ?? $document['path'] ?? null) : $document);

            if ($path === null) {
                return;
            }

            $original = is_array($document)
                ? (string) ($document['original_filename'] ?? basename($path))
                : basename($path);
            $mime = is_array($document) ? ($document['mime_type'] ?? Storage::disk('local')->mimeType($path)) : Storage::disk('local')->mimeType($path);
            $size = is_array($document) ? ($document['file_size'] ?? Storage::disk('local')->size($path)) : Storage::disk('local')->size($path);
        }

        if (! in_array($mime, self::ACCEPTED_MIME_TYPES, true)) {
            throw new \InvalidArgumentException('Supporting documents must be PDF, JPG, JPEG, or PNG files.');
        }

        if ($size !== null && $size > self::MAX_DOCUMENT_SIZE_KB * 1024) {
            throw new \InvalidArgumentException('Supporting documents may not be larger than 5 MB.');
        }

        $request->documents()->create([
            'original_filename' => $original,
            'stored_path' => $path,
            'mime_type' => $mime,
            'file_size' => $size,
            'uploaded_at' => now(),
        ]);
    }

    private function normalizePath(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        return $this->nullableString($value);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
