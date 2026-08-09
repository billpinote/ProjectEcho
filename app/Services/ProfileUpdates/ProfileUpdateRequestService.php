<?php

namespace App\Services\ProfileUpdates;

use App\Domain\ProfileUpdateRequests\Enums\ProfileUpdateRequestStatus;
use App\Domain\Pilots\Enums\PilotQualificationCategory;
use App\Domain\Users\Enums\UserRole;
use App\Models\PilotQualification;
use App\Models\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProfileUpdateRequestService
{
    public const QUALIFICATION_CHANGES_KEY = 'pilot_qualifications';

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
     * @param  array<int, array<string, mixed>>  $qualificationOperations
     */
    public function submit(User $user, array $requestedValues, ?string $reason, array $documents = [], array $qualificationOperations = []): ProfileUpdateRequest
    {
        return DB::transaction(function () use ($user, $requestedValues, $reason, $documents, $qualificationOperations): ProfileUpdateRequest {
            $user->refresh()->loadMissing(['pilotProfile.qualifications', 'dispatchProfile', 'atcProfile', 'avsecProfile']);
            $changes = ProfileFieldRegistry::changesForRequest($user, $requestedValues);
            $qualificationChanges = $this->qualificationChangesForRequest($user, $qualificationOperations);

            if ($qualificationChanges !== []) {
                $changes[self::QUALIFICATION_CHANGES_KEY] = [
                    'label' => 'Ratings & Qualifications',
                    'operations' => $qualificationChanges,
                ];
            }

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
            $user->loadMissing(['pilotProfile.qualifications', 'dispatchProfile', 'atcProfile', 'avsecProfile']);

            foreach ($request->requested_changes ?? [] as $field => $change) {
                if ($field === self::QUALIFICATION_CHANGES_KEY) {
                    $this->applyQualificationOperations($user, $request, $reviewer, $change['operations'] ?? [], $remarks);
                    $user->refresh()->loadMissing(['pilotProfile.qualifications', 'dispatchProfile', 'atcProfile', 'avsecProfile']);

                    continue;
                }

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

    /**
     * @param  array<int, array<string, mixed>>  $operations
     * @return array<int, array<string, mixed>>
     */
    private function qualificationChangesForRequest(User $user, array $operations): array
    {
        $changes = [];

        foreach ($operations as $operation) {
            $type = $operation['operation'] ?? null;

            if ($type === 'add') {
                $new = $this->normalizeQualificationValues($operation['values'] ?? []);

                if (blank($new['category'] ?? null) || blank($new['code'] ?? null)) {
                    continue;
                }

                $changes[] = [
                    'operation' => 'add',
                    'label' => 'Add '.$this->qualificationLabel($new),
                    'new' => $new,
                ];

                continue;
            }

            $qualification = $this->qualificationForUser($user, $operation['qualification_id'] ?? null);

            if ($qualification === null) {
                continue;
            }

            $old = $this->qualificationSnapshot($qualification);

            if ($type === 'remove') {
                $changes[] = [
                    'operation' => 'remove',
                    'qualification_id' => $qualification->getKey(),
                    'label' => 'Remove '.$this->qualificationLabel($old),
                    'old' => $old,
                ];

                continue;
            }

            if ($type !== 'update') {
                continue;
            }

            $new = $this->normalizeQualificationValues($operation['values'] ?? []);
            $fieldChanges = [];

            foreach ($new as $field => $value) {
                if (($old[$field] ?? null) === $value) {
                    continue;
                }

                $fieldChanges[$field] = [
                    'old' => $old[$field] ?? null,
                    'new' => $value,
                ];
            }

            if ($fieldChanges === []) {
                continue;
            }

            $changes[] = [
                'operation' => 'update',
                'qualification_id' => $qualification->getKey(),
                'label' => 'Update '.$this->qualificationLabel($old),
                'old' => $old,
                'new' => $new,
                'changes' => $fieldChanges,
            ];
        }

        return $changes;
    }

    /**
     * @param  array<int, array<string, mixed>>  $operations
     */
    private function applyQualificationOperations(User $user, ProfileUpdateRequest $request, User $reviewer, array $operations, ?string $remarks): void
    {
        foreach ($operations as $operation) {
            $type = $operation['operation'] ?? null;

            if ($type === 'add') {
                $profile = $user->pilotProfile ?: $user->pilotProfile()->create([]);
                $qualification = $profile->qualifications()->create($this->normalizeQualificationValues($operation['new'] ?? []));

                $this->recordQualificationAudit($user, $reviewer, 'pilot_qualification.add', null, $this->qualificationSnapshot($qualification), $qualification, $request, $remarks);

                continue;
            }

            $qualification = $this->qualificationForUser($user, $operation['qualification_id'] ?? null);

            if ($qualification === null) {
                throw new \InvalidArgumentException('Requested qualification was not found for this pilot.');
            }

            if ($type === 'remove') {
                $old = $this->qualificationSnapshot($qualification);
                $this->recordQualificationAudit($user, $reviewer, 'pilot_qualification.remove', $old, null, $qualification, $request, $remarks);
                $qualification->delete();

                continue;
            }

            if ($type !== 'update') {
                throw new \InvalidArgumentException('Unsupported qualification operation.');
            }

            $old = $this->qualificationSnapshot($qualification);
            $qualification->forceFill($this->normalizeQualificationValues($operation['new'] ?? []))->save();
            $qualification->refresh();

            $this->recordQualificationAudit($user, $reviewer, 'pilot_qualification.update', $old, $this->qualificationSnapshot($qualification), $qualification, $request, $remarks);
        }
    }

    private function qualificationForUser(User $user, mixed $qualificationId): ?PilotQualification
    {
        if (blank($qualificationId) || $user->pilotProfile === null) {
            return null;
        }

        return $user->pilotProfile
            ->qualifications()
            ->whereKey($qualificationId)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array{category: string|null, code: string|null, description: string|null, expiry_date: string|null, remarks: string|null}
     */
    private function normalizeQualificationValues(array $values): array
    {
        return [
            'category' => PilotQualificationCategory::tryFrom((string) ($values['category'] ?? ''))?->value,
            'code' => $this->nullableString($values['code'] ?? null),
            'description' => $this->nullableString($values['description'] ?? null),
            'expiry_date' => $this->nullableString($values['expiry_date'] ?? null),
            'remarks' => $this->nullableString($values['remarks'] ?? null),
        ];
    }

    /**
     * @return array{category: string|null, code: string|null, description: string|null, expiry_date: string|null, remarks: string|null}
     */
    private function qualificationSnapshot(PilotQualification $qualification): array
    {
        return [
            'category' => $qualification->category?->value,
            'code' => $qualification->code,
            'description' => $qualification->description,
            'expiry_date' => $qualification->expiry_date?->toDateString(),
            'remarks' => $qualification->remarks,
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function qualificationLabel(array $values): string
    {
        $category = PilotQualificationCategory::tryFrom((string) ($values['category'] ?? ''))?->label();
        $code = trim((string) ($values['code'] ?? ''));

        return trim(implode(' ', array_filter([$category, $code]))) ?: 'Qualification';
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    private function recordQualificationAudit(User $user, User $reviewer, string $field, ?array $old, ?array $new, PilotQualification $qualification, ProfileUpdateRequest $request, ?string $remarks): void
    {
        $this->auditRecorder->recordFieldChange(
            $user,
            $reviewer,
            'profile_change_approved',
            'profile_update_request',
            $field,
            $old === null ? null : $this->qualificationLabel($old),
            $new === null ? null : $this->qualificationLabel($new),
            $qualification,
            $request,
            $remarks,
        );
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
