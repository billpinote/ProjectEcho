<?php

namespace App\Services\ProfileUpdates;

use App\Domain\Users\Enums\UserRole;
use App\Models\Operator;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ProfileFieldRegistry
{
    /**
     * @return array<string, array{relationship: string|null, attribute: string, label: string, type?: string}>
     */
    public static function fields(): array
    {
        return [
            'user.first_name' => ['relationship' => null, 'attribute' => 'first_name', 'label' => 'First Name'],
            'user.middle_name' => ['relationship' => null, 'attribute' => 'middle_name', 'label' => 'Middle Name'],
            'user.last_name' => ['relationship' => null, 'attribute' => 'last_name', 'label' => 'Last Name'],
            'user.suffix' => ['relationship' => null, 'attribute' => 'suffix', 'label' => 'Suffix'],
            'user.display_name' => ['relationship' => null, 'attribute' => 'display_name', 'label' => 'Display Name'],
            'user.employee_id' => ['relationship' => null, 'attribute' => 'employee_id', 'label' => 'Employee ID'],
            'user.operator_id' => ['relationship' => null, 'attribute' => 'operator_id', 'label' => 'Operator', 'type' => 'operator'],
            'user.wiresign' => ['relationship' => null, 'attribute' => 'wiresign', 'label' => 'Wiresign'],
            'user.role' => ['relationship' => null, 'attribute' => 'role', 'label' => 'Role', 'type' => 'role'],
            'user.station' => ['relationship' => null, 'attribute' => 'station', 'label' => 'Station'],
            'pilot_profile.license_number' => ['relationship' => 'pilotProfile', 'attribute' => 'license_number', 'label' => 'Pilot License Number'],
            'pilot_profile.ratings' => ['relationship' => 'pilotProfile', 'attribute' => 'ratings', 'label' => 'Pilot Ratings'],
            'pilot_profile.license_expiry_date' => ['relationship' => 'pilotProfile', 'attribute' => 'license_expiry_date', 'label' => 'License Expiry Date', 'type' => 'date'],
            'pilot_profile.medical_expiry_date' => ['relationship' => 'pilotProfile', 'attribute' => 'medical_expiry_date', 'label' => 'Medical Expiry Date', 'type' => 'date'],
            'pilot_profile.operator' => ['relationship' => 'pilotProfile', 'attribute' => 'operator', 'label' => 'Legacy Operator'],
            'pilot_profile.remarks' => ['relationship' => 'pilotProfile', 'attribute' => 'remarks', 'label' => 'Pilot Remarks'],
            'atc_profile.wiresign' => ['relationship' => 'atcProfile', 'attribute' => 'wiresign', 'label' => 'ATC Wiresign'],
            'atc_profile.facility' => ['relationship' => 'atcProfile', 'attribute' => 'facility', 'label' => 'ATC Facility'],
            'atc_profile.position' => ['relationship' => 'atcProfile', 'attribute' => 'position', 'label' => 'ATC Position'],
            'atc_profile.endorsements' => ['relationship' => 'atcProfile', 'attribute' => 'endorsements', 'label' => 'ATC Endorsements'],
            'atc_profile.remarks' => ['relationship' => 'atcProfile', 'attribute' => 'remarks', 'label' => 'ATC Remarks'],
            'dispatch_profile.dispatcher_license_number' => ['relationship' => 'dispatchProfile', 'attribute' => 'dispatcher_license_number', 'label' => 'Dispatcher License Number'],
            'dispatch_profile.dispatcher_certificate' => ['relationship' => 'dispatchProfile', 'attribute' => 'dispatcher_certificate', 'label' => 'Dispatcher Certificate'],
            'dispatch_profile.department' => ['relationship' => 'dispatchProfile', 'attribute' => 'department', 'label' => 'Dispatch Department'],
            'dispatch_profile.position' => ['relationship' => 'dispatchProfile', 'attribute' => 'position', 'label' => 'Dispatch Position'],
            'dispatch_profile.office_phone' => ['relationship' => 'dispatchProfile', 'attribute' => 'office_phone', 'label' => 'Dispatch Office Phone'],
            'dispatch_profile.mobile_number' => ['relationship' => 'dispatchProfile', 'attribute' => 'mobile_number', 'label' => 'Dispatch Mobile Number'],
            'dispatch_profile.shift' => ['relationship' => 'dispatchProfile', 'attribute' => 'shift', 'label' => 'Dispatch Shift'],
            'dispatch_profile.remarks' => ['relationship' => 'dispatchProfile', 'attribute' => 'remarks', 'label' => 'Dispatch Remarks'],
            'avsec_profile.security_certification' => ['relationship' => 'avsecProfile', 'attribute' => 'security_certification', 'label' => 'Security Certification'],
            'avsec_profile.certification_expiry' => ['relationship' => 'avsecProfile', 'attribute' => 'certification_expiry', 'label' => 'Certification Expiry', 'type' => 'date'],
            'avsec_profile.security_clearance_level' => ['relationship' => 'avsecProfile', 'attribute' => 'security_clearance_level', 'label' => 'Security Clearance Level'],
            'avsec_profile.position' => ['relationship' => 'avsecProfile', 'attribute' => 'position', 'label' => 'AVSEC Position'],
            'avsec_profile.remarks' => ['relationship' => 'avsecProfile', 'attribute' => 'remarks', 'label' => 'AVSEC Remarks'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function optionsFor(User $user): array
    {
        $keys = [
            'user.first_name',
            'user.middle_name',
            'user.last_name',
            'user.suffix',
            'user.employee_id',
            'user.operator_id',
            'user.wiresign',
            'user.station',
        ];

        $keys = match (UserRole::normalize($user->role)) {
            UserRole::Pilot => [...$keys, 'pilot_profile.license_number', 'pilot_profile.ratings', 'pilot_profile.license_expiry_date', 'pilot_profile.medical_expiry_date', 'pilot_profile.operator', 'pilot_profile.remarks'],
            UserRole::Dispatch => [...$keys, 'dispatch_profile.dispatcher_license_number', 'dispatch_profile.dispatcher_certificate', 'dispatch_profile.department', 'dispatch_profile.position', 'dispatch_profile.office_phone', 'dispatch_profile.mobile_number', 'dispatch_profile.shift', 'dispatch_profile.remarks'],
            UserRole::Atmo, UserRole::AtsHq => [...$keys, 'atc_profile.wiresign', 'atc_profile.facility', 'atc_profile.position', 'atc_profile.endorsements', 'atc_profile.remarks'],
            UserRole::Avsec => [...$keys, 'avsec_profile.security_certification', 'avsec_profile.certification_expiry', 'avsec_profile.security_clearance_level', 'avsec_profile.position', 'avsec_profile.remarks'],
            default => $keys,
        };

        return collect($keys)
            ->mapWithKeys(fn (string $key): array => [$key => self::fields()[$key]['label']])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $requestedValues
     * @return array<string, array{old: mixed, new: mixed, label: string}>
     */
    public static function changesForRequest(User $user, array $requestedValues): array
    {
        $changes = [];

        foreach ($requestedValues as $key => $value) {
            if (! array_key_exists($key, self::optionsFor($user))) {
                continue;
            }

            $new = self::normalizeValue($value, self::fields()[$key]['type'] ?? null);
            $old = self::currentValue($user, $key);

            if ($old === $new) {
                continue;
            }

            $changes[$key] = [
                'old' => $old,
                'new' => $new,
                'label' => self::fields()[$key]['label'],
            ];
        }

        return $changes;
    }

    public static function currentValue(User $user, string $key): mixed
    {
        $field = self::fields()[$key] ?? null;

        if ($field === null) {
            throw new \InvalidArgumentException("Profile field [{$key}] is not allowed.");
        }

        $model = self::modelFor($user, $field['relationship']);

        if ($model === null) {
            return null;
        }

        return self::normalizeComparableValue($model->getAttribute($field['attribute']));
    }

    public static function apply(User $user, string $key, mixed $value): Model
    {
        $field = self::fields()[$key] ?? null;

        if ($field === null) {
            throw new \InvalidArgumentException("Profile field [{$key}] is not allowed.");
        }

        $model = self::modelFor($user, $field['relationship'], create: true);
        $model->setAttribute($field['attribute'], self::normalizeValue($value, $field['type'] ?? null));

        if ($model instanceof User) {
            self::refreshDerivedUserName($model);
        }

        $model->save();

        return $model;
    }

    public static function labelForValue(string $key, mixed $value): ?string
    {
        $type = self::fields()[$key]['type'] ?? null;

        if ($type === 'operator' && filled($value)) {
            return Operator::query()->whereKey($value)->value('name');
        }

        if ($type === 'role') {
            return UserRole::normalize($value)?->label();
        }

        return $value === null ? null : (string) $value;
    }

    private static function modelFor(User $user, ?string $relationship, bool $create = false): ?Model
    {
        if ($relationship === null) {
            return $user;
        }

        $loaded = $user->{$relationship};

        if ($loaded !== null || ! $create) {
            return $loaded;
        }

        return $user->{$relationship}()->create([]);
    }

    private static function normalizeValue(mixed $value, ?string $type): mixed
    {
        $value = is_string($value) ? trim($value) : $value;

        if ($value === '') {
            return null;
        }

        if ($type === 'date' && filled($value)) {
            return (string) $value;
        }

        return $value;
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

    private static function refreshDerivedUserName(User $user): void
    {
        $fullName = trim(implode(' ', array_filter([
            $user->first_name,
            $user->middle_name,
            $user->last_name,
            $user->suffix,
        ])));

        if ($fullName !== '') {
            $user->name = $fullName;
        }

        if (blank($user->display_name)) {
            $user->display_name = $user->name;
        }
    }
}
