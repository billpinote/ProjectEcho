<?php

namespace Tests\Feature;

use App\Domain\FlightPlans\Enums\FlightPlanStatus;
use App\Domain\Pilots\Enums\PilotLicenseType;
use App\Domain\Pilots\Enums\PilotQualificationCategory;
use App\Domain\Users\Enums\UserRole;
use App\Filament\Shared\Resources\Flights\Pages\CreateFlight;
use App\Models\Flight;
use App\Models\Operator;
use App\Models\PilotProfile;
use App\Models\User;
use Database\Seeders\StudentPilotSeeder;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class StudentPilotSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_pilot_seeder_is_idempotent_and_uses_normal_pilot_role(): void
    {
        $this->seed(StudentPilotSeeder::class);
        $this->seed(StudentPilotSeeder::class);

        $operator = Operator::query()->where('name', 'Alpha Aviation Group')->firstOrFail();
        $pilot = User::query()
            ->where('email', 'juan.delacruz@example.com')
            ->with(['operator', 'pilotProfile.qualifications', 'authAccounts'])
            ->firstOrFail();
        $profile = $pilot->pilotProfile;

        $this->assertSame(1, User::query()->where('email', 'juan.delacruz@example.com')->count());
        $this->assertSame('Juan Dela Cruz', $pilot->name);
        $this->assertSame('juan.delacruz', $pilot->username);
        $this->assertSame(UserRole::Pilot, $pilot->role);
        $this->assertTrue($pilot->is_active);
        $this->assertTrue(Hash::check('password', $pilot->password));
        $this->assertTrue($pilot->operator->is($operator));
        $this->assertTrue($pilot->canAccessPanel(Panel::make()->id('pilot')));
        $this->assertSame(1, $pilot->authAccounts()->where('provider', 'password')->count());

        $this->assertNotNull($profile);
        $this->assertSame(1, $pilot->pilotProfile()->count());
        $this->assertSame(PilotLicenseType::StudentPilot, $profile->license_type);
        $this->assertSame('SPL-DEV-001', $profile->license_number);
        $this->assertSame('2028-12-31', $profile->license_expiry_date?->toDateString());
        $this->assertSame('2027-12-31', $profile->medical_expiry_date?->toDateString());

        $this->assertSame(2, $profile->qualifications->count());
        $this->assertDatabaseHas('pilot_qualifications', [
            'pilot_profile_id' => $profile->id,
            'category' => PilotQualificationCategory::Endorsement->value,
            'code' => 'SPL',
            'description' => 'Student Pilot Authorization',
        ]);
        $this->assertDatabaseHas('pilot_qualifications', [
            'pilot_profile_id' => $profile->id,
            'category' => PilotQualificationCategory::AircraftRating->value,
            'code' => 'C172-STUDENT',
        ]);
    }

    public function test_seeded_student_pilot_can_prepare_for_another_pic_without_becoming_pic(): void
    {
        $this->seed(StudentPilotSeeder::class);

        Filament::setCurrentPanel('pilot');

        $studentPilot = User::query()
            ->where('email', 'juan.delacruz@example.com')
            ->with('pilotProfile')
            ->firstOrFail();

        $this->actingAs($studentPilot)
            ->get(route('filament.pilot.resources.flights.create'))
            ->assertOk();

        Livewire::actingAs($studentPilot)
            ->test(CreateFlight::class)
            ->fillForm(['filing_capacity' => 'for_another_pic'])
            ->assertSee('Awaiting PIC identification. Verified PIC credentials will be completed during PIC authorization.')
            ->assertFormSet([
                'authorized_representative_enabled' => true,
                'authorized_representative_name' => 'JUAN DELA CRUZ',
                'authorized_representative_role' => 'PILOT',
                'pilot_in_command' => null,
                'pilot_license_no' => null,
                'pilot_ratings' => null,
                'license_expiry_date' => null,
            ])
            ->fillForm($this->validFlightPlanFormData([
                'filing_capacity' => 'for_another_pic',
                'pilot_in_command' => 'JUAN DELA CRUZ',
                'pilot_license_no' => $studentPilot->pilotProfile instanceof PilotProfile
                    ? $studentPilot->pilotProfile->formatted_license
                    : 'SPL-SPL-DEV-001',
                'pilot_ratings' => 'SPL',
                'license_expiry_date' => '2028-12-31',
            ]))
            ->call('create')
            ->assertHasNoFormErrors();

        $flight = Flight::query()->latest('id')->firstOrFail();

        $this->assertSame(FlightPlanStatus::AwaitingPic, $flight->status);
        $this->assertSame($studentPilot->id, $flight->prepared_by_user_id);
        $this->assertSame('JUAN DELA CRUZ', $flight->prepared_by_name);
        $this->assertSame('PILOT', $flight->prepared_by_role);
        $this->assertNull($flight->pilot_id);
        $this->assertNull($flight->pilot_in_command_user_id);
        $this->assertNull($flight->pilot_in_command);
        $this->assertNull($flight->pilot_license_no);
        $this->assertNull($flight->pilot_ratings);
        $this->assertNull($flight->license_expiry_date);
        $this->assertTrue($flight->authorized_representative_enabled);
        $this->assertSame('JUAN DELA CRUZ', $flight->authorized_representative_name);
        $this->assertTrue($flight->requiresPicAuthorization());
        $this->assertFalse($flight->canSubmitToAtc());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validFlightPlanFormData(array $overrides = []): array
    {
        $date = now('Asia/Manila')->addDay();

        return [
            'date_of_flight' => $date->toDateString(),
            'aircraft_identification' => 'SPL123',
            'flight_rules' => 'V',
            'type_of_flight' => 'G',
            'number' => '1',
            'type_of_aircraft' => 'C172',
            'wake_turbulence_cat' => 'L',
            'equipment_10a' => 'S',
            'equipment_10b' => 'C',
            'departure_aerodrome' => 'RPUS',
            'proposed_time' => '1430',
            'cruising_speed' => 'N100',
            'level' => 'VFR',
            'route' => 'DCT',
            'destination_aerodrome' => 'RPLL',
            'total_eet' => '0100',
            'endurance' => '0300',
            'persons_on_board' => '2',
            'other_information' => 'DOF/'.$date->format('Ymd'),
            'pilot_in_command' => 'TEST PIC',
            'pilot_license_no' => 'CPL-123456',
            'pilot_ratings' => 'C172',
            'license_expiry_date' => $date->copy()->addYear()->toDateString(),
            'dinghies_enabled' => false,
            'authorized_representative_enabled' => false,
            ...$overrides,
        ];
    }
}
