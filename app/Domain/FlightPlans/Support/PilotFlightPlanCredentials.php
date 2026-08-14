<?php

namespace App\Domain\FlightPlans\Support;

use App\Domain\Pilots\Enums\PilotQualificationCategory;
use App\Models\PilotProfile;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class PilotFlightPlanCredentials
{
    private const CATEGORY_ORDER = [
        PilotQualificationCategory::AircraftRating->value => 10,
        PilotQualificationCategory::InstrumentRating->value => 20,
        PilotQualificationCategory::InstructorRating->value => 30,
        PilotQualificationCategory::Endorsement->value => 40,
        PilotQualificationCategory::Other->value => 50,
    ];

    /**
     * @return array{
     *     pilot_name: ?string,
     *     license: ?string,
     *     ratings: ?string,
     *     license_expiry_date: ?string,
     *     profile_exists: bool,
     *     license_valid: bool
     * }
     */
    public static function forUser(User $user, mixed $dateOfFlight = null): array
    {
        $profile = $user->pilotProfile()->with('qualifications')->first();
        $effectiveDate = self::effectiveDate($dateOfFlight);
        $licenseExpiry = $profile?->license_expiry_date;

        return [
            'pilot_name' => trim((string) $user->fullName()) ?: null,
            'license' => $profile?->formattedLicense(),
            'ratings' => self::ratingsSnapshot($profile, $effectiveDate),
            'license_expiry_date' => $licenseExpiry?->toDateString(),
            'profile_exists' => $profile !== null,
            'license_valid' => $licenseExpiry !== null && $licenseExpiry->gte($effectiveDate),
        ];
    }

    public static function ratingsForUser(User $user, mixed $dateOfFlight = null): ?string
    {
        return self::forUser($user, $dateOfFlight)['ratings'];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function applySnapshot(array $data, User $user): array
    {
        $credentials = self::forUser($user, $data['date_of_flight'] ?? null);

        $data['user_id'] = $user->id;
        $data['pilot_id'] = $user->id;
        $data['pilot_in_command_user_id'] = $user->id;
        $data['pilot_in_command'] = $credentials['pilot_name'];
        $data['pilot_license_no'] = $credentials['license'];
        $data['pilot_ratings'] = $credentials['ratings'];
        $data['license_expiry_date'] = $credentials['license_expiry_date'];

        return $data;
    }

    /**
     * @return array<string, string>
     */
    public static function validationMessages(User $user, mixed $dateOfFlight = null): array
    {
        $credentials = self::forUser($user, $dateOfFlight);
        $messages = [];

        if (! $credentials['profile_exists']) {
            $messages['pilot_license_no'] = 'Your verified pilot profile is missing. Contact an administrator to update your verified pilot profile.';
        }

        if (blank($credentials['pilot_name'])) {
            $messages['pilot_in_command'] = 'Your verified pilot name is missing. Contact an administrator to update your verified pilot profile.';
        }

        if (blank($credentials['license'])) {
            $messages['pilot_license_no'] = 'Your verified pilot license is missing. Contact an administrator to update your verified pilot profile.';
        }

        if (blank($credentials['license_expiry_date'])) {
            $messages['license_expiry_date'] = 'Your verified pilot license expiry date is missing. Contact an administrator to update your verified pilot profile.';
        } elseif (! $credentials['license_valid']) {
            $messages['license_expiry_date'] = 'Your pilot license expires before the selected Date of Flight. Contact an administrator to update your verified pilot profile.';
        }

        return $messages;
    }

    private static function ratingsSnapshot(?PilotProfile $profile, CarbonInterface $dateOfFlight): ?string
    {
        if ($profile === null) {
            return null;
        }

        $codes = $profile->qualifications
            ->filter(fn ($qualification): bool => filled($qualification->code))
            ->filter(fn ($qualification): bool => $qualification->expiry_date === null || $qualification->expiry_date->gte($dateOfFlight))
            ->sortBy([
                fn ($a, $b): int => (self::CATEGORY_ORDER[$a->category?->value ?? ''] ?? 999) <=> (self::CATEGORY_ORDER[$b->category?->value ?? ''] ?? 999),
                fn ($a, $b): int => strtoupper(trim((string) $a->code)) <=> strtoupper(trim((string) $b->code)),
            ])
            ->map(fn ($qualification): string => strtoupper(trim((string) $qualification->code)))
            ->unique()
            ->values();

        if ($codes->isNotEmpty()) {
            return $codes->implode(' ');
        }

        $legacyRatings = strtoupper(trim((string) $profile->ratings));

        return $legacyRatings === '' ? null : $legacyRatings;
    }

    private static function effectiveDate(mixed $dateOfFlight): CarbonInterface
    {
        if ($dateOfFlight instanceof CarbonInterface) {
            return $dateOfFlight->copy()->startOfDay();
        }

        if (filled($dateOfFlight)) {
            return Carbon::parse($dateOfFlight)->startOfDay();
        }

        return now('UTC')->startOfDay();
    }
}
