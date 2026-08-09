<?php

namespace Tests\Feature;

use App\Domain\Pilots\Enums\PilotLicenseType;
use App\Domain\Pilots\Enums\PilotQualificationCategory;
use App\Domain\Users\Enums\UserRole;
use App\Models\User;
use Database\Seeders\PilotProfileSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PilotProfileSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_pilot_profile_seeder_creates_one_idempotent_development_pilot_profile(): void
    {
        $this->seed(PilotProfileSeeder::class);
        $this->seed(PilotProfileSeeder::class);

        $pilot = User::query()
            ->where('email', 'pilot@example.com')
            ->with(['operator', 'pilotProfile.qualifications', 'authAccounts'])
            ->firstOrFail();

        $this->assertSame(1, User::query()->where('email', 'pilot@example.com')->count());
        $this->assertSame('Jesse James', $pilot->first_name);
        $this->assertSame('Vita', $pilot->middle_name);
        $this->assertSame('Superales', $pilot->last_name);
        $this->assertSame('pilot', $pilot->username);
        $this->assertSame(UserRole::Pilot, $pilot->role);
        $this->assertTrue($pilot->is_active);
        $this->assertTrue(Hash::check('password', $pilot->password));
        $this->assertSame('Alpha Aviation Group', $pilot->operator?->name);
        $this->assertSame(1, $pilot->authAccounts()->where('provider', 'password')->count());

        $profile = $pilot->pilotProfile;

        $this->assertNotNull($profile);
        $this->assertSame(1, $pilot->pilotProfile()->count());
        $this->assertSame(PilotLicenseType::CommercialPilot, $profile->license_type);
        $this->assertSame('987654', $profile->license_number);
        $this->assertSame('2028-12-31', $profile->license_expiry_date?->toDateString());
        $this->assertSame('2027-12-31', $profile->medical_expiry_date?->toDateString());
        $this->assertNull($profile->operator);

        $this->assertSame(3, $profile->qualifications->count());
        $this->assertDatabaseHas('pilot_qualifications', [
            'pilot_profile_id' => $profile->id,
            'category' => PilotQualificationCategory::AircraftRating->value,
            'code' => 'C172',
            'description' => 'Cessna 172',
            'expiry_date' => null,
        ]);
        $this->assertDatabaseHas('pilot_qualifications', [
            'pilot_profile_id' => $profile->id,
            'category' => PilotQualificationCategory::AircraftRating->value,
            'code' => 'C208',
            'description' => 'Cessna 208',
            'expiry_date' => null,
        ]);
        $this->assertDatabaseHas('pilot_qualifications', [
            'pilot_profile_id' => $profile->id,
            'category' => PilotQualificationCategory::InstrumentRating->value,
            'code' => 'IR',
            'description' => 'Instrument Rating',
        ]);
        $this->assertSame(
            '2027-12-31',
            $profile->qualifications->firstWhere('code', 'IR')?->expiry_date?->toDateString(),
        );
    }
}
