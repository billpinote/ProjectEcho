<?php

namespace Tests\Feature;

use App\Enums\FlightPlanStatus;
use App\Enums\UserRole;
use App\Filament\Resources\AcceptedFlights\Pages\ListAcceptedFlights;
use App\Filament\Resources\ActiveFlights\Pages\ListActiveFlights;
use App\Filament\Resources\AirborneFlights\Pages\ListAirborneFlights;
use App\Filament\Resources\CompletedFlights\CompletedFlightResource;
use App\Filament\Resources\LandedFlights\Pages\ListLandedFlights;
use App\Filament\Widgets\AlphaFlightsTable;
use App\Models\Flight;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class FlightOperationsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_panel_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('filament.atmo.resources.flights.index'));
        $this->assertTrue(Route::has('filament.atmo.resources.accepted-flights.index'));
        $this->assertTrue(Route::has('filament.atmo.resources.active-flights.index'));
        $this->assertTrue(Route::has('filament.atmo.resources.airborne-flights.index'));
        $this->assertTrue(Route::has('filament.atmo.resources.landed-flights.index'));
        $this->assertTrue(Route::has('filament.atmo.resources.completed-flights.index'));
        $this->assertTrue(Route::has('filament.atmo.resources.expired-flights.index'));
        $this->assertTrue(Route::has('filament.atmo.resources.rejected-flights.index'));
        $this->assertTrue(Route::has('filament.atmo.resources.all-flight-plans.index'));
        $this->assertTrue(Route::has('filament.atmo.pages.coordinator'));
        $this->assertTrue(Route::has('filament.atmo.pages.import-scan-qr'));

        $this->assertTrue(Route::has('filament.dispatch.resources.accepted-flights.index'));
        $this->assertTrue(Route::has('filament.dispatch.resources.active-flights.index'));
        $this->assertTrue(Route::has('filament.dispatch.resources.landed-flights.index'));
        $this->assertTrue(Route::has('filament.dispatch.resources.completed-flights.index'));
    }

    public function test_atmo_sidebar_renders_operational_navigation_for_atmo_and_artisan_users(): void
    {
        foreach ([
            'atmo' => $this->user(UserRole::Atmo, ['station' => 'RPUS']),
            'artisan' => $this->user(UserRole::Artisan),
        ] as $user) {
            $response = $this->actingAs($user)->get('/atmo');

            $response
                ->assertOk()
                ->assertSeeText('Pending Flight Plans')
                ->assertSeeText('Accepted Flights')
                ->assertSeeText('Active Flights')
                ->assertSeeText('Airborne Flights')
                ->assertSeeText('Landed Flights')
                ->assertSeeText('Completed Flights')
                ->assertSeeText('Expired Flights')
                ->assertSeeText('Rejected Flights')
                ->assertSeeText('All Flights')
                ->assertSeeText('Coordinator')
                ->assertSeeText('QR Import')
                ->assertSeeText('Alpha');
        }
    }

    public function test_dispatch_sidebar_renders_dispatch_operational_navigation(): void
    {
        $response = $this->actingAs($this->user(UserRole::Dispatch))->get('/dispatch');

        $response
            ->assertOk()
            ->assertSeeText('Accepted Flights')
            ->assertSeeText('Active Flights')
            ->assertSeeText('Landed Flights')
            ->assertSeeText('Completed Flights')
            ->assertSeeText('QR Import')
            ->assertDontSeeText('Pending Flight Plans')
            ->assertDontSeeText('Airborne Flights')
            ->assertDontSeeText('Rejected Flights')
            ->assertDontSeeText('All Flights');
    }

    public function test_direct_operational_urls_open_for_their_panel_users(): void
    {
        $atmo = $this->user(UserRole::Atmo, ['station' => 'RPUS']);
        $dispatch = $this->user(UserRole::Dispatch);

        foreach ([
            '/atmo/flights',
            '/atmo/accepted-flights',
            '/atmo/active-flights',
            '/atmo/airborne-flights',
            '/atmo/landed-flights',
            '/atmo/completed-flights',
            '/atmo/expired-flights',
            '/atmo/rejected-flights',
            '/atmo/all-flight-plans',
            '/atmo/coordinator',
            '/atmo/import-scan-qr',
            '/atmo/alpha',
        ] as $url) {
            $this->actingAs($atmo)->get($url)->assertOk();
        }

        foreach ([
            '/dispatch/accepted-flights',
            '/dispatch/active-flights',
            '/dispatch/landed-flights',
            '/dispatch/completed-flights',
            '/dispatch/import-scan-qr',
        ] as $url) {
            $this->actingAs($dispatch)->get($url)->assertOk();
        }
    }

    public function test_operational_time_columns_render_in_the_expected_tables(): void
    {
        $atmo = $this->user(UserRole::Atmo, ['station' => 'RPUS']);

        $this->actingAs($atmo)
            ->get('/atmo/accepted-flights')
            ->assertOk()
            ->assertSeeText('START-UP TIME')
            ->assertSeeText('OFF-BLOCK TIME')
            ->assertDontSeeText('TAKE OFF TIME');

        $activeFlight = $this->flight([
            'status' => FlightPlanStatus::Accepted,
            'time_start_up' => '08:10',
        ]);

        $this->assertTrue(Flight::query()->active()->whereKey($activeFlight)->exists());

        $blockOffOnlyFlight = $this->flight([
            'status' => FlightPlanStatus::Accepted,
            'time_block_off' => '08:20',
        ]);

        $this->assertTrue(Flight::query()->active()->whereKey($blockOffOnlyFlight)->exists());

        $this->actingAs($atmo)
            ->get('/atmo/active-flights')
            ->assertOk()
            ->assertSeeText('TAKE OFF TIME')
            ->assertSeeText('AIRBORNE')
            ->assertSeeText('START-UP TIME')
            ->assertDontSeeText('OFF-BLOCK TIME')
            ->assertDontSeeText('OFF-BLOCK');

        Livewire::actingAs($atmo)
            ->test(AlphaFlightsTable::class)
            ->assertSee('START-UP TIME')
            ->assertSee('OFF-BLOCK TIME');
    }

    public function test_atmo_can_approve_and_reject_pending_flight_plans(): void
    {
        Storage::fake('public');
        $atmo = $this->user(UserRole::Atmo, ['station' => 'RPUS', 'wiresign' => 'AT']);

        $acceptedFlight = $this->flight(['status' => FlightPlanStatus::Pending]);

        $this->actingAs($atmo)
            ->post(route('flights.accept', $acceptedFlight))
            ->assertRedirect(route('flights.view', $acceptedFlight));

        $acceptedFlight->refresh();

        $this->assertSame(FlightPlanStatus::Accepted, $acceptedFlight->status);
        $this->assertSame($atmo->id, $acceptedFlight->accepted_by_user_id);
        $this->assertSame('AT', $acceptedFlight->accepted_by_wiresign);

        $rejectedFlight = $this->flight(['status' => FlightPlanStatus::Pending]);

        $this->actingAs($atmo)
            ->post(route('flights.reject', $rejectedFlight), ['rejection_reason' => 'Incomplete route'])
            ->assertRedirect(route('flights.view', $rejectedFlight));

        $rejectedFlight->refresh();

        $this->assertSame(FlightPlanStatus::Rejected, $rejectedFlight->status);
        $this->assertSame('AT', $rejectedFlight->rejected_by_wiresign);
        $this->assertSame('Incomplete route', $rejectedFlight->rejection_reason);
    }

    public function test_unauthorized_roles_cannot_review_or_enter_atmo_times(): void
    {
        $pilot = $this->user(UserRole::Pilot);
        $dispatch = $this->user(UserRole::Dispatch);

        $this->assertFalse($pilot->canReviewFlightPlans());
        $this->assertFalse($pilot->canUpdateFlightPlans());
        $this->assertFalse($dispatch->canReviewFlightPlans());
        $this->assertFalse($dispatch->canUpdateFlightPlans());

        $this->actingAs($pilot)
            ->post(route('flights.accept', $this->flight(['status' => FlightPlanStatus::Pending])))
            ->assertForbidden();

        $this->actingAs($dispatch)
            ->post(route('flights.reject', $this->flight(['status' => FlightPlanStatus::Pending])), [
                'rejection_reason' => 'No',
            ])
            ->assertForbidden();
    }

    public function test_complete_flight_lifecycle_uses_existing_operational_timestamps(): void
    {
        Storage::fake('public');

        $atmo = $this->user(UserRole::Atmo, ['station' => 'RPUS', 'wiresign' => 'AT']);
        $dispatch = $this->user(UserRole::Dispatch);
        $flight = $this->flight(['status' => FlightPlanStatus::Pending]);

        $this->actingAs($atmo)
            ->post(route('flights.accept', $flight))
            ->assertRedirect(route('flights.view', $flight));

        $flight->refresh();
        $this->assertSame(FlightPlanStatus::Accepted, $flight->status);
        $this->assertTrue(Flight::query()->ready()->whereKey($flight)->exists());

        $this->actingAs($dispatch);
        (new ListAcceptedFlights())->confirmStartUpNow($flight->id);
        $flight->refresh();
        $this->assertNotNull($flight->time_start_up);
        $this->assertTrue(Flight::query()->active()->whereKey($flight)->exists());

        (new ListAcceptedFlights())->confirmBlockOffNow($flight->id);
        $flight->refresh();
        $this->assertNotNull($flight->time_block_off);

        $this->actingAs($atmo);
        (new ListActiveFlights())->confirmAirborneNow($flight->id);
        $flight->refresh();
        $this->assertNotNull($flight->time_airborne);
        $this->assertTrue(Flight::query()->airborne()->whereKey($flight)->exists());

        (new ListAirborneFlights())->confirmTouchdownNow($flight->id);
        $flight->refresh();
        $this->assertNotNull($flight->time_touchdown);
        $this->assertTrue(Flight::query()->landed()->whereKey($flight)->exists());

        $this->actingAs($dispatch);
        (new ListLandedFlights())->confirmShutdownNow($flight->id);
        $flight->refresh();
        $this->assertNotNull($flight->time_shutdown);
        $this->assertTrue(CompletedFlightResource::getEloquentQuery()->whereKey($flight)->exists());
    }

    public function test_artisan_can_access_every_panel_and_operational_permission(): void
    {
        $artisan = $this->user(UserRole::Artisan);

        $this->assertTrue($artisan->canReviewFlightPlans());
        $this->assertTrue($artisan->canUpdateFlightPlans());
        $this->assertTrue($artisan->canUpdateFlightStartUpTime());
        $this->assertTrue($artisan->canUpdateFlightBlockOffTime());
        $this->assertTrue($artisan->canUpdateFlightShutdownTime());
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
