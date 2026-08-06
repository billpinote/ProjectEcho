<?php

namespace Tests\Feature;

use App\Enums\FlightPlanStatus;
use App\Enums\UserRole;
use App\Filament\Resources\AcceptedFlights\AcceptedFlightResource;
use App\Filament\Resources\Flights\FlightResource;
use App\Filament\Resources\Flights\Pages\EditFlight;
use App\Filament\Resources\MyArchivedFlights\Pages\ListMyArchivedFlights;
use App\Filament\Resources\MyCompletedFlights\Pages\ListMyCompletedFlights;
use App\Filament\Resources\MyCurrentFlights\Pages\ListMyCurrentFlights;
use App\Models\Flight;
use App\Models\User;
use App\Services\FlightPlanMutationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class FlightPlanPilotAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pilot_cannot_open_general_edit_page_for_a_submitted_flight(): void
    {
        $pilot = $this->user(UserRole::Pilot);
        $flight = $this->flight(['filed_by_user_id' => $pilot->id]);

        $this->assertTrue(Route::has('filament.pilot.resources.flights.edit'));
        $this->assertFalse(Route::has('filament.pilot.resources.my-current-flights.edit'));

        $this->actingAs($pilot)
            ->get(route('filament.pilot.resources.flights.edit', ['record' => $flight]))
            ->assertForbidden();
    }

    public function test_pilot_cannot_open_the_pending_flight_plans_queue(): void
    {
        $pilot = $this->user(UserRole::Pilot);
        $pendingFlight = $this->flight([
            'aircraft_identification' => 'QUEUE01',
            'filed_by_user_id' => $this->user(UserRole::Pilot)->id,
        ]);

        $this->actingAs($pilot)
            ->get(route('filament.pilot.resources.flights.index'))
            ->assertForbidden();

        $this->actingAs($pilot)
            ->get(route('filament.pilot.resources.flights.create'))
            ->assertOk()
            ->assertDontSeeText((string) $pendingFlight->aircraft_identification);
    }

    public function test_pilot_cannot_forge_a_livewire_update_request_for_a_submitted_flight(): void
    {
        $pilot = $this->user(UserRole::Pilot);
        $flight = $this->flight(['filed_by_user_id' => $pilot->id]);

        Livewire::actingAs($pilot)
            ->test(EditFlight::class, ['record' => $flight->getKey()])
            ->assertForbidden();
    }

    public function test_my_flight_plans_are_scoped_by_filed_by_user_id_and_do_not_expose_edit(): void
    {
        $pilot = $this->user(UserRole::Pilot);
        $ownedFlight = $this->flight([
            'filed_by_user_id' => $pilot->id,
            'aircraft_identification' => 'OWN123',
        ]);
        $otherFlight = $this->flight([
            'filed_by_user_id' => $this->user(UserRole::Pilot)->id,
            'aircraft_identification' => 'OTH456',
        ]);

        $this->actingAs($pilot)
            ->get(route('filament.pilot.resources.my-current-flights.index'))
            ->assertOk()
            ->assertSeeText('OWN123')
            ->assertDontSeeText('OTH456');

        Livewire::actingAs($pilot)
            ->test(ListMyCurrentFlights::class)
            ->assertTableActionDoesNotExist('edit', null, $ownedFlight->getKey())
            ->assertTableActionVisible('view', $ownedFlight->getKey())
            ->assertTableActionVisible('delay', $ownedFlight->getKey())
            ->assertTableActionVisible('cancel', $ownedFlight->getKey())
            ->assertDontSee('OTH456');
    }

    public function test_my_flight_plans_include_owned_records_regardless_of_status(): void
    {
        $pilot = $this->user(UserRole::Pilot);
        $expiredFlight = $this->flight([
            'filed_by_user_id' => $pilot->id,
            'aircraft_identification' => 'EXP001',
            'status' => FlightPlanStatus::Pending,
            'date_of_flight' => now('Asia/Manila')->subDay()->toDateString(),
        ]);
        $completedFlight = $this->flight([
            'filed_by_user_id' => $pilot->id,
            'aircraft_identification' => 'CMP001',
            'status' => FlightPlanStatus::Accepted,
            'time_start_up' => '08:10',
            'time_block_off' => '08:20',
            'time_airborne' => '08:30',
            'time_touchdown' => '09:10',
            'time_shutdown' => '09:20',
        ]);

        $this->actingAs($pilot)
            ->get(route('filament.pilot.resources.my-archived-flights.index'))
            ->assertOk()
            ->assertSeeText((string) $expiredFlight->aircraft_identification);

        $this->actingAs($pilot)
            ->get(route('filament.pilot.resources.my-completed-flights.index'))
            ->assertOk()
            ->assertSeeText((string) $completedFlight->aircraft_identification);
    }

    public function test_pilot_sees_their_own_flights_in_current_completed_and_archived_sections_only(): void
    {
        $pilot = $this->user(UserRole::Pilot);
        $currentFlight = $this->flight([
            'filed_by_user_id' => $pilot->id,
            'aircraft_identification' => 'CUR001',
            'status' => FlightPlanStatus::Accepted,
        ]);
        $completedFlight = $this->flight([
            'filed_by_user_id' => $pilot->id,
            'aircraft_identification' => 'COM001',
            'status' => FlightPlanStatus::Accepted,
            'time_start_up' => '08:10',
            'time_block_off' => '08:20',
            'time_airborne' => '08:30',
            'time_touchdown' => '09:10',
            'time_shutdown' => '09:20',
        ]);
        $archivedFlight = $this->flight([
            'filed_by_user_id' => $pilot->id,
            'aircraft_identification' => 'ARC001',
            'status' => FlightPlanStatus::Rejected,
        ]);

        $this->actingAs($pilot)
            ->get(route('filament.pilot.resources.my-current-flights.index'))
            ->assertOk()
            ->assertSeeText('CUR001')
            ->assertDontSeeText('COM001')
            ->assertDontSeeText('ARC001');

        $this->actingAs($pilot)
            ->get(route('filament.pilot.resources.my-completed-flights.index'))
            ->assertOk()
            ->assertSeeText('COM001')
            ->assertDontSeeText('CUR001')
            ->assertDontSeeText('ARC001');

        $this->actingAs($pilot)
            ->get(route('filament.pilot.resources.my-archived-flights.index'))
            ->assertOk()
            ->assertSeeText('ARC001')
            ->assertDontSeeText('CUR001')
            ->assertDontSeeText('COM001');
    }

    public function test_pilot_cannot_view_delay_or_cancel_another_pilots_flight(): void
    {
        $owner = $this->user(UserRole::Pilot);
        $intruder = $this->user(UserRole::Pilot);
        $flight = $this->flight(['filed_by_user_id' => $owner->id]);

        $this->actingAs($intruder)
            ->get(route('flights.view', $flight))
            ->assertForbidden();

        Livewire::actingAs($intruder)
            ->test(ListMyCurrentFlights::class)
            ->assertDontSee((string) $flight->aircraft_identification);

        try {
            app(FlightPlanMutationService::class)->delay($flight, $intruder, '1530');
            $this->fail('Expected delay authorization to be denied.');
        } catch (AuthorizationException) {
        }

        try {
            app(FlightPlanMutationService::class)->cancel($flight, $intruder, 'No longer flying');
            $this->fail('Expected cancellation authorization to be denied.');
        } catch (AuthorizationException) {
        }
    }

    public function test_pilot_can_delay_only_their_own_eligible_flight_and_only_proposed_time_changes(): void
    {
        $pilot = $this->user(UserRole::Pilot);
        $flight = $this->flight([
            'filed_by_user_id' => $pilot->id,
            'status' => FlightPlanStatus::Accepted,
            'route' => 'DCT TEST',
        ]);

        Livewire::actingAs($pilot)
            ->test(ListMyCurrentFlights::class)
            ->callTableAction('delay', $flight->getKey(), ['new_proposed_time' => '1530'])
            ->assertHasNoTableActionErrors();

        $flight->refresh();

        $this->assertSame('15:30', $flight->proposed_time);
        $this->assertSame('DCT TEST', $flight->route);
        $this->assertSame(FlightPlanStatus::Accepted, $flight->status);

        $this->assertDatabaseHas('flight_plan_events', [
            'flight_id' => $flight->id,
            'actor_user_id' => $pilot->id,
            'event_type' => 'delayed',
        ]);

        $event = $flight->events()->where('event_type', 'delayed')->latest('id')->firstOrFail();

        $this->assertSame('14:30', $event->old_values['proposed_time']);
        $this->assertSame('15:30', $event->new_values['proposed_time']);
    }

    public function test_delay_is_blocked_after_startup_off_block_or_airborne_activity(): void
    {
        $pilot = $this->user(UserRole::Pilot);

        foreach ([
            ['time_start_up' => '08:10'],
            ['time_block_off' => '08:20'],
            ['time_start_up' => '08:10', 'time_airborne' => '08:30'],
        ] as $attributes) {
            $flight = $this->flight([
                'filed_by_user_id' => $pilot->id,
                'status' => FlightPlanStatus::Accepted,
                ...$attributes,
            ]);

            Livewire::actingAs($pilot)
                ->test(ListMyCurrentFlights::class)
                ->assertTableActionDisabled('delay', $flight->getKey());
        }
    }

    public function test_pilot_can_cancel_their_own_eligible_flight_without_deleting_the_record(): void
    {
        $pilot = $this->user(UserRole::Pilot);
        $atmo = $this->user(UserRole::Atmo, ['station' => 'RPUS']);
        $flight = $this->flight([
            'filed_by_user_id' => $pilot->id,
            'status' => FlightPlanStatus::Accepted,
        ]);

        Livewire::actingAs($pilot)
            ->test(ListMyCurrentFlights::class)
            ->callTableAction('cancel', $flight->getKey(), ['reason' => 'Weather'])
            ->assertHasNoTableActionErrors();

        $flight->refresh();

        $this->assertDatabaseHas('flights', [
            'id' => $flight->id,
            'status' => FlightPlanStatus::Cancelled->value,
            'cancelled_by_user_id' => $pilot->id,
        ]);
        $this->assertNotNull($flight->cancelled_at);

        $this->actingAs($atmo);

        $this->assertFalse(AcceptedFlightResource::getEloquentQuery()->whereKey($flight)->exists());
        $this->assertFalse(FlightResource::getEloquentQuery()->whereKey($flight)->exists());

        $event = $flight->events()->where('event_type', 'cancelled')->latest('id')->firstOrFail();

        $this->assertSame('accepted', $event->old_values['status']);
        $this->assertSame('cancelled', $event->new_values['status']);
        $this->assertSame('Weather', $event->reason);
    }

    public function test_cancellation_is_blocked_after_startup_off_block_or_airborne_activity(): void
    {
        $pilot = $this->user(UserRole::Pilot);

        foreach ([
            ['time_start_up' => '08:10'],
            ['time_block_off' => '08:20'],
            ['time_start_up' => '08:10', 'time_airborne' => '08:30'],
        ] as $attributes) {
            $flight = $this->flight([
                'filed_by_user_id' => $pilot->id,
                'status' => FlightPlanStatus::Accepted,
                ...$attributes,
            ]);

            Livewire::actingAs($pilot)
                ->test(ListMyCurrentFlights::class)
                ->assertTableActionDisabled('cancel', $flight->getKey());
        }
    }

    public function test_admin_and_artisan_retain_general_edit_access(): void
    {
        $flight = $this->flight();
        $admin = $this->user(UserRole::Admin);
        $artisan = $this->user(UserRole::Artisan);

        $this->assertTrue($admin->can('update', $flight));

        $this->actingAs($artisan)
            ->get(route('filament.artisan.resources.flights.edit', ['record' => $flight]))
            ->assertOk();
    }

    public function test_historical_flights_with_null_filed_by_user_id_are_not_claimable_by_pilots(): void
    {
        $pilot = $this->user(UserRole::Pilot);
        $historicalFlight = $this->flight([
            'user_id' => $pilot->id,
            'filed_by_user_id' => null,
        ]);

        $this->actingAs($pilot)
            ->get(route('flights.view', $historicalFlight))
            ->assertForbidden();

        Livewire::actingAs($pilot)
            ->test(ListMyCurrentFlights::class)
            ->assertDontSee((string) $historicalFlight->aircraft_identification);

        Livewire::actingAs($pilot)
            ->test(ListMyCompletedFlights::class)
            ->assertDontSee((string) $historicalFlight->aircraft_identification);

        Livewire::actingAs($pilot)
            ->test(ListMyArchivedFlights::class)
            ->assertDontSee((string) $historicalFlight->aircraft_identification);

        try {
            app(FlightPlanMutationService::class)->delay($historicalFlight, $pilot, '1530');
            $this->fail('Expected historical flight delay authorization to be denied.');
        } catch (AuthorizationException) {
        }

        try {
            app(FlightPlanMutationService::class)->cancel($historicalFlight, $pilot, 'Legacy');
            $this->fail('Expected historical flight cancellation authorization to be denied.');
        } catch (AuthorizationException) {
        }
    }

    public function test_pilot_cannot_download_or_view_another_pilots_pdf_or_qr_asset(): void
    {
        Storage::fake('public');

        $owner = $this->user(UserRole::Pilot);
        $intruder = $this->user(UserRole::Pilot);
        $flight = $this->flight([
            'filed_by_user_id' => $owner->id,
            'aircraft_identification' => 'SEC001',
        ]);

        $this->actingAs($intruder)
            ->get(route('flights.pdf.download', $flight))
            ->assertForbidden();

        $this->actingAs($intruder)
            ->get(route('flights.qr', $flight))
            ->assertForbidden();

        $this->actingAs($intruder)
            ->get(route('flights.qr.download', $flight))
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function user(UserRole $role, array $attributes = []): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function flight(array $attributes = []): Flight
    {
        return Flight::create([
            'status' => FlightPlanStatus::Pending,
            'date_of_flight' => now('Asia/Manila')->addDay()->toDateString(),
            'proposed_time' => '1430',
            'aircraft_identification' => 'RPC123',
            'departure_aerodrome' => 'RPUS',
            'destination_aerodrome' => 'RPLL',
            'route' => 'DCT',
            ...$attributes,
        ]);
    }
}
