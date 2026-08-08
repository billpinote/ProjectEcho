<?php

namespace App\Filament\Shared\Resources\Users\Support;

use App\Domain\Users\Enums\UserRole;
use App\Models\Operator;
use App\Models\User;
use App\Models\UserAuditLog;
use App\Models\UserKycDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class UserAccountFormData
{
    private const PROFILE_FIELDS = [
        'pilot_license_number',
        'pilot_ratings',
        'pilot_license_expiry_date',
        'pilot_medical_expiry_date',
        'pilot_remarks',
        'dispatch_dispatcher_license_number',
        'dispatch_dispatcher_certificate',
        'dispatch_department',
        'dispatch_position',
        'dispatch_office_phone',
        'dispatch_mobile_number',
        'dispatch_shift',
        'dispatch_remarks',
        'atc_wiresign',
        'atc_facility',
        'atc_position',
        'atc_endorsements',
        'atc_remarks',
        'avsec_security_certification',
        'avsec_certification_expiry',
        'avsec_security_clearance_level',
        'avsec_position',
        'avsec_remarks',
    ];

    private const SENSITIVE_FIELDS = [
        'password',
        'remember_token',
    ];

    /**
     * @param  array<string, mixed>  $data
     * @return array{user: array<string, mixed>, profiles: array<string, mixed>}
     */
    public static function split(array $data): array
    {
        $profiles = [];

        foreach ([...self::PROFILE_FIELDS, 'kyc_documents'] as $field) {
            if (array_key_exists($field, $data)) {
                $profiles[$field] = $data[$field];
                unset($data[$field]);
            }
        }

        $data = self::normalizeUserData($data);

        return [
            'user' => $data,
            'profiles' => $profiles,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fillProfileData(User $user, array $data): array
    {
        $user->loadMissing(['pilotProfile', 'dispatchProfile', 'atcProfile', 'avsecProfile']);

        return [
            ...$data,
            'password' => null,
            'kyc_documents' => [],
            'pilot_license_number' => $user->pilotProfile?->license_number,
            'pilot_ratings' => $user->pilotProfile?->ratings,
            'pilot_license_expiry_date' => $user->pilotProfile?->license_expiry_date?->toDateString(),
            'pilot_medical_expiry_date' => $user->pilotProfile?->medical_expiry_date?->toDateString(),
            'pilot_remarks' => $user->pilotProfile?->remarks,
            'dispatch_dispatcher_license_number' => $user->dispatchProfile?->dispatcher_license_number,
            'dispatch_dispatcher_certificate' => $user->dispatchProfile?->dispatcher_certificate,
            'dispatch_department' => $user->dispatchProfile?->department,
            'dispatch_position' => $user->dispatchProfile?->position,
            'dispatch_office_phone' => $user->dispatchProfile?->office_phone,
            'dispatch_mobile_number' => $user->dispatchProfile?->mobile_number,
            'dispatch_shift' => $user->dispatchProfile?->shift,
            'dispatch_remarks' => $user->dispatchProfile?->remarks,
            'atc_wiresign' => $user->atcProfile?->wiresign,
            'atc_facility' => $user->atcProfile?->facility,
            'atc_position' => $user->atcProfile?->position,
            'atc_endorsements' => $user->atcProfile?->endorsements,
            'atc_remarks' => $user->atcProfile?->remarks,
            'avsec_security_certification' => $user->avsecProfile?->security_certification,
            'avsec_certification_expiry' => $user->avsecProfile?->certification_expiry?->toDateString(),
            'avsec_security_clearance_level' => $user->avsecProfile?->security_clearance_level,
            'avsec_position' => $user->avsecProfile?->position,
            'avsec_remarks' => $user->avsecProfile?->remarks,
        ];
    }

    /**
     * @param  array<string, mixed>  $profileData
     */
    public static function syncProfile(User $user, array $profileData, ?User $actor = null): void
    {
        $role = UserRole::normalize($user->role);

        match ($role) {
            UserRole::Pilot => self::syncOwnedProfile($user, $actor, 'pilotProfile', [
                'license_number' => self::nullableString($profileData['pilot_license_number'] ?? null),
                'ratings' => self::nullableString($profileData['pilot_ratings'] ?? null),
                'license_expiry_date' => $profileData['pilot_license_expiry_date'] ?: null,
                'medical_expiry_date' => $profileData['pilot_medical_expiry_date'] ?: null,
                'remarks' => self::nullableString($profileData['pilot_remarks'] ?? null),
            ], ['license_number', 'ratings', 'license_expiry_date', 'medical_expiry_date', 'remarks']),
            UserRole::Dispatch => self::syncOwnedProfile($user, $actor, 'dispatchProfile', [
                'dispatcher_license_number' => self::nullableString($profileData['dispatch_dispatcher_license_number'] ?? null),
                'dispatcher_certificate' => self::nullableString($profileData['dispatch_dispatcher_certificate'] ?? null),
                'department' => self::nullableString($profileData['dispatch_department'] ?? null),
                'position' => self::nullableString($profileData['dispatch_position'] ?? null),
                'office_phone' => self::nullableString($profileData['dispatch_office_phone'] ?? null),
                'mobile_number' => self::nullableString($profileData['dispatch_mobile_number'] ?? null),
                'shift' => self::nullableString($profileData['dispatch_shift'] ?? null),
                'remarks' => self::nullableString($profileData['dispatch_remarks'] ?? null),
            ], ['dispatcher_license_number', 'dispatcher_certificate', 'department', 'position', 'office_phone', 'mobile_number', 'shift', 'remarks']),
            UserRole::Atmo, UserRole::AtsHq => self::syncOwnedProfile($user, $actor, 'atcProfile', [
                'wiresign' => self::nullableString($profileData['atc_wiresign'] ?? $user->wiresign),
                'facility' => self::nullableString($profileData['atc_facility'] ?? null),
                'position' => self::nullableString($profileData['atc_position'] ?? null),
                'endorsements' => self::nullableString($profileData['atc_endorsements'] ?? null),
                'remarks' => self::nullableString($profileData['atc_remarks'] ?? null),
            ], ['wiresign', 'facility', 'position', 'endorsements', 'remarks']),
            UserRole::Avsec => self::syncOwnedProfile($user, $actor, 'avsecProfile', [
                'security_certification' => self::nullableString($profileData['avsec_security_certification'] ?? null),
                'certification_expiry' => $profileData['avsec_certification_expiry'] ?: null,
                'security_clearance_level' => self::nullableString($profileData['avsec_security_clearance_level'] ?? null),
                'position' => self::nullableString($profileData['avsec_position'] ?? null),
                'remarks' => self::nullableString($profileData['avsec_remarks'] ?? null),
            ], ['security_certification', 'certification_expiry', 'security_clearance_level', 'position', 'remarks']),
            default => null,
        };
    }

    public static function syncPasswordAuthAccount(User $user, ?string $password): void
    {
        $password = trim((string) ($password ?? ''));

        if ($password === '') {
            return;
        }

        $user->forceFill([
            'password' => $password,
        ])->save();

        $user->authAccounts()->updateOrCreate(
            ['provider' => 'password'],
            [
                'identifier' => $user->email,
                'email' => $user->email,
                'password_hash' => Hash::make($password),
                'email_verified_at' => now(),
            ],
        );
    }

    public static function syncAuthAccountIdentity(User $user): void
    {
        $user->authAccounts()
            ->where('provider', 'password')
            ->update([
                'identifier' => $user->email,
                'email' => $user->email,
            ]);
    }

    public static function recordCreated(User $user, ?User $actor): void
    {
        self::recordAudit($user, $actor, 'user_created', 'user_created', $user, [], 'User account created.');
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public static function recordUserChanges(User $user, ?User $actor, array $changes): void
    {
        if ($changes === []) {
            return;
        }

        $action = match (true) {
            array_key_exists('role', $changes) => 'role_changed',
            array_key_exists('operator_id', $changes) => 'operator_changed',
            array_key_exists('is_active', $changes) && ($changes['is_active']['new'] ?? null) === false => 'deactivated',
            array_key_exists('is_active', $changes) && ($changes['is_active']['new'] ?? null) === true => 'activated',
            default => 'updated',
        };

        self::recordAudit(
            $user,
            $actor,
            $action,
            'admin_direct_change',
            $user,
            $changes,
            self::describeChanges($changes),
        );
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public static function recordProfileChanges(User $user, ?User $actor, Model $profile, array $changes, bool $created): void
    {
        if (! $created && $changes === []) {
            return;
        }

        self::recordAudit(
            $user,
            $actor,
            $created ? 'profile_created' : 'updated',
            'admin_direct_change',
            $profile,
            $changes,
            $created ? class_basename($profile).' created.' : self::describeChanges($changes),
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $documents
     */
    public static function syncKycDocuments(User $user, ?User $actor, array $documents): void
    {
        foreach ($documents as $documentData) {
            if (! is_array($documentData) || blank($documentData['document_type'] ?? null)) {
                continue;
            }

            $document = $user->kycDocuments()->create([
                'document_type' => (string) $documentData['document_type'],
                'document_identifier' => self::nullableString($documentData['document_identifier'] ?? null),
                'file_path' => self::normalizeUploadedFilePath($documentData['file_path'] ?? null),
                'verified_by_user_id' => $actor?->getKey(),
                'verified_at' => now(),
                'remarks' => self::nullableString($documentData['remarks'] ?? null),
            ]);

            self::recordAudit(
                $user,
                $actor,
                'kyc_recorded',
                'kyc_verified',
                $document,
                [
                    'document_type' => [
                        'old' => null,
                        'new' => $document->document_type,
                        'new_label' => $document->documentTypeLabel(),
                    ],
                    'document_identifier' => [
                        'old' => null,
                        'new' => $document->maskedIdentifier(),
                    ],
                    'file_path' => [
                        'old' => null,
                        'new' => filled($document->file_path) ? 'stored_private_attachment' : null,
                    ],
                ],
                'KYC document recorded: '.$document->documentTypeLabel().'.',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $newAttributes
     * @param  array<int, string>  $only
     * @return array<string, mixed>
     */
    public static function changesFor(Model $model, array $newAttributes, array $only): array
    {
        $changes = [];

        foreach ($only as $field) {
            if (in_array($field, self::SENSITIVE_FIELDS, true) || ! array_key_exists($field, $newAttributes)) {
                continue;
            }

            $old = self::normalizeComparableValue($model->getAttribute($field));
            $new = self::normalizeComparableValue($newAttributes[$field]);

            if ($old === $new) {
                continue;
            }

            $changes[$field] = [
                'old' => $old,
                'new' => $new,
            ];

            if ($field === 'operator_id') {
                $changes[$field]['old_label'] = self::operatorLabel($old);
                $changes[$field]['new_label'] = self::operatorLabel($new);
            }

            if ($field === 'role') {
                $changes[$field]['old_label'] = UserRole::normalize($old)?->label();
                $changes[$field]['new_label'] = UserRole::normalize($new)?->label();
            }
        }

        return $changes;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function normalizeUserData(array $data): array
    {
        foreach (['first_name', 'middle_name', 'last_name', 'suffix', 'display_name', 'username', 'employee_id', 'wiresign', 'station'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = self::nullableString($data[$field]);
            }
        }

        if (array_key_exists('email', $data)) {
            $data['email'] = strtolower(trim((string) $data['email']));
        }

        $fullName = trim(implode(' ', array_filter([
            $data['first_name'] ?? null,
            $data['middle_name'] ?? null,
            $data['last_name'] ?? null,
            $data['suffix'] ?? null,
        ])));

        $data['name'] = $fullName !== ''
            ? $fullName
            : ($data['display_name'] ?: ($data['email'] ?? 'New User'));

        if (blank($data['display_name'] ?? null)) {
            $data['display_name'] = $data['name'];
        }

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        return $data;
    }

    private static function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private static function recordAudit(
        User $user,
        ?User $actor,
        string $action,
        ?string $source,
        ?Model $auditable,
        array $changes,
        ?string $description,
    ): UserAuditLog {
        return $user->auditLogs()->create([
            'performed_by_user_id' => $actor?->getKey(),
            'action' => $action,
            'source' => $source,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'changes' => $changes === [] ? null : $changes,
            'description' => $description,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, string>  $auditedFields
     */
    private static function syncOwnedProfile(
        User $user,
        ?User $actor,
        string $relationship,
        array $attributes,
        array $auditedFields,
    ): void {
        $existing = $user->{$relationship}()->first();
        $created = $existing === null;
        $changes = $existing === null ? [] : self::changesFor($existing, $attributes, $auditedFields);
        $profile = $user->{$relationship}()->updateOrCreate([], $attributes);

        self::recordProfileChanges($user, $actor, $profile, $changes, $created);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private static function describeChanges(array $changes): string
    {
        return collect($changes)
            ->keys()
            ->map(fn (string $field): string => str($field)->replace('_', ' ')->headline()->toString())
            ->implode(', ').' changed.';
    }

    private static function normalizeComparableValue(mixed $value): mixed
    {
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return $value;
    }

    private static function operatorLabel(mixed $operatorId): ?string
    {
        if (blank($operatorId)) {
            return null;
        }

        return Operator::query()->whereKey($operatorId)->value('name');
    }

    private static function normalizeUploadedFilePath(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        return self::nullableString($value);
    }
}
