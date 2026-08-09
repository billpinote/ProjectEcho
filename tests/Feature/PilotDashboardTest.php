<?php

namespace Tests\Feature;

use App\Domain\FlightPlans\Enums\FlightPlanStatus;
use App\Domain\Pilots\Enums\PilotLicenseType;
use App\Domain\Pilots\Enums\PilotQualificationCategory;
use App\Domain\Users\Enums\UserRole;
use App\Filament\Panels\Pilot\Pages\Dashboard;
use App\Models\Flight;
use App\Models\Operator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PilotDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_for_pilot_with_display_name_greeting_and_readiness(): void
    {
        $pilot = $this->pilot([
            'display_name' => 'Jesse',
            'first_name' => 'Jesse James',
            'operator_id' => Operator::factory()->create(['name' => 'Alpha Aviation Group'])->id,
        ]);

        $profile = $pilot->pilotProfile()->create([
            'license_type' => PilotLicenseType::CommercialPilot,
            'license_number' => '987654',
            'license_expiry_date' => now()->addYear()->toDateString(),
            'medical_expiry_date' => now()->addYear()->toDateString(),
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
                'expiry_date' => now()->addYear()->toDateString(),
            ],
            [
                'category' => PilotQualificationCategory::InstructorRating,
                'code' => 'FI',
                'description' => 'Flight Instructor',
                'expiry_date' => now()->subDay()->toDateString(),
            ],
        ]);

        $this->actingAs($pilot)
            ->get('/pilot')
            ->assertOk()
            ->assertSeeText('Jesse')
            ->assertSeeText('CPL-987654')
            ->assertSeeText('Alpha Aviation Group')
            ->assertSeeText('Licence')
            ->assertSeeText('Medical')
            ->assertSeeText('Valid')
            ->assertSeeText('2 Active')
            ->assertSee(route('filament.pilot.resources.flights.create'), false)
            ->assertDontSeeText('Flight Instructor');
    }

    public function test_dashboard_keeps_browser_title_but_uses_custom_visual_header_instead_of_page_heading(): void
    {
        $dashboard = app(Dashboard::class);

        $this->assertSame('Dashboard', $dashboard->getTitle());
        $this->assertNull($dashboard->getHeading());
    }

    public function test_dashboard_greeting_falls_back_to_first_name(): void
    {
        $pilot = $this->pilot([
            'display_name' => null,
            'first_name' => 'Maya',
            'last_name' => 'Pilot',
        ]);

        $this->actingAs($pilot)
            ->get('/pilot')
            ->assertOk()
            ->assertSeeText('Maya');
    }

    public function test_dashboard_readiness_surfaces_expiring_and_expired_states(): void
    {
        $pilot = $this->pilot();
        $pilot->pilotProfile()->create([
            'license_type' => PilotLicenseType::PrivatePilot,
            'license_number' => 'PPL-1',
            'license_expiry_date' => now()->subDay()->toDateString(),
            'medical_expiry_date' => now()->addDays(24)->toDateString(),
        ]);

        $this->actingAs($pilot)
            ->get('/pilot')
            ->assertOk()
            ->assertSeeText('Expired')
            ->assertSeeText('Expiring Soon')
            ->assertSeeText('Expires in 24 days');
    }

    public function test_dashboard_shows_empty_states_for_new_pilot(): void
    {
        $pilot = $this->pilot();

        $this->actingAs($pilot)
            ->get('/pilot')
            ->assertOk()
            ->assertSeeText('No pending flight plans.')
            ->assertSeeText('No accepted flights ready for departure.')
            ->assertSeeText('No active flights right now.')
            ->assertSeeText('No flights yet. File your first flight plan when you are ready.')
            ->assertSeeText('File Flight Plan');
    }

    public function test_dashboard_ticket_cards_render_current_and_recent_flight_statuses(): void
    {
        $pilot = $this->pilot();

        $pending = $this->flight($pilot, [
            'aircraft_identification' => 'PEND01',
            'status' => FlightPlanStatus::Pending,
        ]);
        $accepted = $this->flight($pilot, [
            'aircraft_identification' => 'ACPT01',
            'status' => FlightPlanStatus::Accepted,
        ]);
        $active = $this->flight($pilot, [
            'aircraft_identification' => 'ACTV01',
            'status' => FlightPlanStatus::Accepted,
            'time_start_up' => '08:10',
        ]);
        $completed = $this->flight($pilot, [
            'aircraft_identification' => 'DONE01',
            'status' => FlightPlanStatus::Accepted,
            'time_start_up' => '08:10',
            'time_block_off' => '08:20',
            'time_airborne' => '08:30',
            'time_touchdown' => '09:15',
            'time_shutdown' => '09:25',
        ]);

        $this->actingAs($pilot)
            ->get('/pilot')
            ->assertOk()
            ->assertSeeText('Current Flights')
            ->assertSeeText('Recent Flights')
            ->assertSeeText('PEND01')
            ->assertSeeText('Awaiting Approval')
            ->assertSeeText('ACPT01')
            ->assertSeeText('Approved')
            ->assertSeeText('ACTV01')
            ->assertSeeText('Preparing for Departure')
            ->assertSeeText('DONE01')
            ->assertSeeText('Flight Complete')
            ->assertSee(route('flights.view', $pending), false)
            ->assertSee(route('flights.qr', $accepted), false)
            ->assertSee(route('flights.view', $active), false)
            ->assertSee(route('flights.view', $completed), false)
            ->assertSeeText('RPUS')
            ->assertSeeText('RPLL')
            ->assertSeeText('0830Z')
            ->assertSeeText('C208')
            ->assertSeeText('V');
    }

    public function test_dashboard_does_not_leak_other_pilots_flights(): void
    {
        $pilot = $this->pilot();
        $otherPilot = $this->pilot(['email' => 'other-pilot@example.test']);

        $this->flight($pilot, ['aircraft_identification' => 'MINE01']);
        $this->flight($otherPilot, ['aircraft_identification' => 'OTHR01']);

        $this->actingAs($pilot)
            ->get('/pilot')
            ->assertOk()
            ->assertSeeText('MINE01')
            ->assertDontSeeText('OTHR01');
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

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function flight(User $pilot, array $attributes = []): Flight
    {
        return Flight::create([
            'filed_by_user_id' => $pilot->id,
            'pilot_id' => $pilot->id,
            'status' => FlightPlanStatus::Pending,
            'date_of_flight' => now('Asia/Manila')->addDay()->toDateString(),
            'proposed_time' => '0830',
            'aircraft_identification' => 'RP-C1234',
            'departure_aerodrome' => 'RPUS',
            'destination_aerodrome' => 'RPLL',
            'type_of_aircraft' => 'C208',
            'flight_rules' => 'V',
            'route' => 'DCT',
            ...$attributes,
        ]);
    }
}
