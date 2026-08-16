<?php

namespace Tests\Feature;

use App\Domain\Pilots\Enums\PilotLicenseType;
use App\Domain\Pilots\Enums\PilotQualificationCategory;
use App\Domain\Users\Enums\UserRole;
use App\Models\PilotProfile;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PilotQualificationModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_pilot_profile_can_have_multiple_qualifications(): void
    {
        $profile = PilotProfile::factory()->create([
            'user_id' => User::factory()->create(['role' => UserRole::Pilot])->id,
            'license_type' => PilotLicenseType::CommercialPilot,
            'license_number' => '123456',
        ]);

        $profile->qualifications()->createMany([
            [
                'category' => PilotQualificationCategory::AircraftRating,
                'code' => 'C172',
                'description' => 'Cessna 172',
            ],
            [
                'category' => PilotQualificationCategory::InstrumentRating,
                'code' => 'IR',
                'description' => 'Instrument Rating',
                'expiry_date' => '2027-05-01',
            ],
        ]);

        $profile->refresh()->load('qualifications');

        $this->assertSame(PilotLicenseType::CommercialPilot, $profile->license_type);
        $this->assertSame('123456', $profile->license_number);
        $this->assertSame(2, $profile->qualifications->count());
        $this->assertTrue($profile->qualifications->every(fn ($qualification): bool => $qualification->pilotProfile->is($profile)));
    }

    public function test_qualification_expiry_dates_are_cast_correctly(): void
    {
        $profile = PilotProfile::factory()->create([
            'user_id' => User::factory()->create(['role' => UserRole::Pilot])->id,
        ]);

        $qualification = $profile->qualifications()->create([
            'category' => PilotQualificationCategory::InstructorRating,
            'code' => 'FI',
            'expiry_date' => '2027-08-15',
        ]);

        $this->assertSame(PilotQualificationCategory::InstructorRating, $qualification->refresh()->category);
        $this->assertSame('2027-08-15', $qualification->expiry_date?->toDateString());
    }

    public function test_pilot_licence_display_is_formatted_without_combining_stored_fields(): void
    {
        $profile = PilotProfile::factory()->create([
            'user_id' => User::factory()->create(['role' => UserRole::Pilot])->id,
            'license_type' => PilotLicenseType::CommercialPilot,
            'license_number' => '987654',
        ]);

        $this->assertSame('CPL-987654', $profile->formatted_license);
        $this->assertSame('CPL-987654', $profile->formattedLicense());
        $this->assertSame(PilotLicenseType::CommercialPilot, $profile->license_type);
        $this->assertSame('987654', $profile->license_number);
    }

    public function test_pilot_licence_display_uses_the_canonical_formatter_for_all_license_types(): void
    {
        foreach ([
            PilotLicenseType::StudentPilot,
            PilotLicenseType::PrivatePilot,
            PilotLicenseType::CommercialPilot,
            PilotLicenseType::AirlineTransportPilot,
        ] as $licenseType) {
            $profile = PilotProfile::factory()->create([
                'user_id' => User::factory()->create(['role' => UserRole::Pilot])->id,
                'license_type' => $licenseType,
                'license_number' => '123456',
            ]);

            $this->assertSame($licenseType->value.'-123456', $profile->formattedLicense());
        }
    }

    public function test_pilot_licence_formatter_handles_partial_values(): void
    {
        $this->assertSame('CPL', PilotProfile::formatLicense(PilotLicenseType::CommercialPilot, null));
        $this->assertSame('987654', PilotProfile::formatLicense(null, '987654'));
        $this->assertNull(PilotProfile::formatLicense(null, null));
    }

    public function test_legacy_ratings_data_is_preserved_as_imported_qualification(): void
    {
        Schema::dropIfExists('pilot_qualifications');

        Schema::table('pilot_profiles', function (Blueprint $table): void {
            if (Schema::hasColumn('pilot_profiles', 'license_type')) {
                $table->dropColumn('license_type');
            }
        });

        $profile = PilotProfile::query()->create([
            'user_id' => User::factory()->create(['role' => UserRole::Pilot])->id,
            'license_number' => 'LEG-100',
            'ratings' => 'IR ME C208',
        ]);

        $migration = require database_path('migrations/2026_08_09_000001_add_license_type_and_pilot_qualifications.php');
        $migration->up();

        $this->assertDatabaseHas('pilot_profiles', [
            'id' => $profile->id,
            'ratings' => 'IR ME C208',
        ]);
        $this->assertDatabaseHas('pilot_qualifications', [
            'pilot_profile_id' => $profile->id,
            'category' => PilotQualificationCategory::Other->value,
            'code' => 'LEGACY',
            'description' => 'IR ME C208',
            'remarks' => 'Imported from legacy pilot_profiles.ratings.',
        ]);
    }
}
