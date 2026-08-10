<?php

namespace Tests\Feature;

use App\Domain\FlightPlans\Support\PilotFlightPlanCredentials;
use App\Domain\Pilots\Enums\PilotLicenseType;
use App\Domain\Pilots\Enums\PilotQualificationCategory;
use App\Domain\Users\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PilotFlightPlanCredentialsTest extends TestCase
{
    use RefreshDatabase;

    public function test_credentials_use_verified_name_formatted_license_and_active_qualification_codes(): void
    {
        $pilot = $this->pilot([
            'first_name' => 'Verified',
            'middle_name' => null,
            'last_name' => 'Pilot',
            'suffix' => null,
        ]);

        $profile = $pilot->pilotProfile()->create([
            'license_type' => PilotLicenseType::CommercialPilot,
            'license_number' => '123456',
            'ratings' => 'LEGACY',
            'license_expiry_date' => '2026-08-31',
        ]);

        $profile->qualifications()->createMany([
            ['category' => PilotQualificationCategory::AircraftRating, 'code' => 'c172', 'expiry_date' => '2026-08-15'],
            ['category' => PilotQualificationCategory::InstrumentRating, 'code' => 'ir', 'expiry_date' => '2026-08-20'],
            ['category' => PilotQualificationCategory::InstructorRating, 'code' => 'fi', 'expiry_date' => '2026-08-09'],
            ['category' => PilotQualificationCategory::Endorsement, 'code' => '  ', 'expiry_date' => null],
            ['category' => PilotQualificationCategory::Other, 'code' => 'night', 'expiry_date' => null],
        ]);

        $credentials = PilotFlightPlanCredentials::forUser($pilot, '2026-08-14');

        $this->assertSame('Verified Pilot', $credentials['pilot_name']);
        $this->assertSame('CPL-123456', $credentials['license']);
        $this->assertSame('C172 IR NIGHT', $credentials['ratings']);
        $this->assertSame('2026-08-31', $credentials['license_expiry_date']);
    }

    public function test_qualification_expiry_is_evaluated_against_date_of_flight(): void
    {
        $pilot = $this->pilot();
        $profile = $pilot->pilotProfile()->create([
            'license_type' => PilotLicenseType::PrivatePilot,
            'license_number' => '654321',
            'license_expiry_date' => '2026-12-31',
        ]);
        $profile->qualifications()->create([
            'category' => PilotQualificationCategory::AircraftRating,
            'code' => 'C172',
            'expiry_date' => '2026-08-15',
        ]);

        $this->assertSame('C172', PilotFlightPlanCredentials::ratingsForUser($pilot, '2026-08-15'));
        $this->assertNull(PilotFlightPlanCredentials::ratingsForUser($pilot, '2026-08-16'));
    }

    public function test_duplicate_codes_are_removed_after_uppercasing(): void
    {
        $pilot = $this->pilot();
        $profile = $pilot->pilotProfile()->create([
            'license_type' => PilotLicenseType::PrivatePilot,
            'license_number' => '654321',
            'license_expiry_date' => '2026-12-31',
        ]);
        $profile->qualifications()->createMany([
            ['category' => PilotQualificationCategory::AircraftRating, 'code' => 'c172'],
            ['category' => PilotQualificationCategory::Other, 'code' => 'C172'],
        ]);

        $this->assertSame('C172', PilotFlightPlanCredentials::ratingsForUser($pilot, '2026-08-14'));
    }

    public function test_legacy_profile_ratings_are_used_only_when_no_usable_qualification_codes_exist(): void
    {
        $pilot = $this->pilot();
        $profile = $pilot->pilotProfile()->create([
            'license_type' => PilotLicenseType::AirlineTransportPilot,
            'license_number' => '777',
            'ratings' => 'legacy ir',
            'license_expiry_date' => '2026-12-31',
        ]);
        $profile->qualifications()->create([
            'category' => PilotQualificationCategory::AircraftRating,
            'code' => '',
            'expiry_date' => null,
        ]);

        $this->assertSame('LEGACY IR', PilotFlightPlanCredentials::ratingsForUser($pilot, '2026-08-14'));

        $profile->qualifications()->create([
            'category' => PilotQualificationCategory::AircraftRating,
            'code' => 'C172',
            'expiry_date' => null,
        ]);

        $this->assertSame('C172', PilotFlightPlanCredentials::ratingsForUser($pilot, '2026-08-14'));
    }

    public function test_license_expiring_before_date_of_flight_is_invalid(): void
    {
        $pilot = $this->pilot();
        $pilot->pilotProfile()->create([
            'license_type' => PilotLicenseType::CommercialPilot,
            'license_number' => '123456',
            'license_expiry_date' => '2026-08-15',
        ]);

        $this->assertSame([], PilotFlightPlanCredentials::validationMessages($pilot, '2026-08-15'));
        $this->assertSame(
            'Your pilot license expires before the selected Date of Flight. Contact an administrator to update your verified pilot profile.',
            PilotFlightPlanCredentials::validationMessages($pilot, '2026-08-16')['license_expiry_date'],
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function pilot(array $attributes = []): User
    {
        return User::factory()->create([
            'role' => UserRole::Pilot,
            'is_active' => true,
            ...$attributes,
        ]);
    }
}
