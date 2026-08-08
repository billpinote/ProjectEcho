<?php

namespace Tests\Feature;

use App\Domain\FlightPlans\Enums\FlightPlanStatus;
use App\Domain\FlightPlans\Services\FlightPlanQrPayloadService;
use App\Domain\Users\Enums\UserRole;
use App\Filament\Panels\Dispatch\Resources\AcceptedFlights\AcceptedFlightResource;
use App\Filament\Panels\Dispatch\Resources\AcceptedFlights\Pages\ListAcceptedFlights;
use App\Filament\Panels\Dispatch\Resources\ActiveFlights\ActiveFlightResource;
use App\Filament\Panels\Dispatch\Resources\LandedFlights\Pages\ListLandedFlights;
use App\Filament\Shared\Resources\Flights\FlightResource;
use App\Models\Flight;
use App\Models\Operator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class FlightOperatorIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_leading_edge_dispatch_can_see_and_update_leading_edge_flights_only(): void
    {
        [$leadingEdge, $alpha] = $this->operators();
        $dispatch = $this->user(UserRole::Dispatch, ['operator_id' => $leadingEdge->id]);
        $leadingFlight = $this->flight($leadingEdge, [
            'aircraft_identification' => 'LEAD01',
            'status' => FlightPlanStatus::Accepted,
        ]);
        $alphaFlight = $this->flight($alpha, [
            'aircraft_identification' => 'ALPHA01',
            'status' => FlightPlanStatus::Accepted,
        ]);

        $this->actingAs($dispatch);

        $this->assertTrue(AcceptedFlightResource::getEloquentQuery()->whereKey($leadingFlight)->exists());
        $this->assertFalse(AcceptedFlightResource::getEloquentQuery()->whereKey($alphaFlight)->exists());

        Livewire::actingAs($dispatch)
            ->test(ListAcceptedFlights::class)
            ->assertSee('LEAD01')
            ->assertDontSee('ALPHA01')
            ->call('confirmStartUpNow', $leadingFlight->id)
            ->assertHasNoErrors();

        $this->assertNotNull($leadingFlight->refresh()->time_start_up);
        $this->assertNull($alphaFlight->refresh()->time_start_up);
    }

    public function test_leading_edge_dispatch_cannot_access_alpha_flight_routes_or_qr_data(): void
    {
        Storage::fake('public');

        [$leadingEdge, $alpha] = $this->operators();
        $dispatch = $this->user(UserRole::Dispatch, ['operator_id' => $leadingEdge->id]);
        $alphaFlight = $this->flight($alpha, [
            'aircraft_identification' => 'ALPHA01',
            'status' => FlightPlanStatus::Accepted,
        ]);

        $this->actingAs($dispatch)
            ->get(route('flights.view', $alphaFlight))
            ->assertForbidden();

        $this->actingAs($dispatch)
            ->get(route('flights.pdf.download', $alphaFlight))
            ->assertForbidden();

        $this->actingAs($dispatch)
            ->get(route('flights.qr', $alphaFlight))
            ->assertForbidden();

        $this->actingAs($dispatch)
            ->get(route('flights.qr.download', $alphaFlight))
            ->assertForbidden();

        $this->assertNotNull(app(FlightPlanQrPayloadService::class)->buildPayload($alphaFlight));
    }

    public function test_manual_dispatch_record_url_for_other_operator_is_forbidden_or_not_found(): void
    {
        [$leadingEdge, $alpha] = $this->operators();
        $dispatch = $this->user(UserRole::Dispatch, ['operator_id' => $leadingEdge->id]);
        $alphaFlight = $this->flight($alpha, ['status' => FlightPlanStatus::Accepted]);

        $response = $this->actingAs($dispatch)
            ->get(route('filament.dispatch.resources.accepted-flights.edit', ['record' => $alphaFlight]));

        $this->assertContains($response->getStatusCode(), [403, 404]);
    }

    public function test_alpha_dispatch_has_inverse_operator_isolation(): void
    {
        [$leadingEdge, $alpha] = $this->operators();
        $dispatch = $this->user(UserRole::Dispatch, ['operator_id' => $alpha->id]);
        $leadingFlight = $this->flight($leadingEdge, [
            'aircraft_identification' => 'LEAD01',
            'status' => FlightPlanStatus::Accepted,
        ]);
        $alphaFlight = $this->flight($alpha, [
            'aircraft_identification' => 'ALPHA01',
            'status' => FlightPlanStatus::Accepted,
        ]);

        $this->actingAs($dispatch);

        $this->assertFalse(AcceptedFlightResource::getEloquentQuery()->whereKey($leadingFlight)->exists());
        $this->assertTrue(AcceptedFlightResource::getEloquentQuery()->whereKey($alphaFlight)->exists());

        Livewire::actingAs($dispatch)
            ->test(ListAcceptedFlights::class)
            ->assertDontSee('LEAD01')
            ->assertSee('ALPHA01');
    }

    public function test_dispatch_cannot_operationally_update_another_operator_flight_by_forged_id(): void
    {
        [$leadingEdge, $alpha] = $this->operators();
        $dispatch = $this->user(UserRole::Dispatch, ['operator_id' => $leadingEdge->id]);
        $alphaFlight = $this->flight($alpha, ['status' => FlightPlanStatus::Accepted]);

        $this->actingAs($dispatch);

        try {
            (new ListAcceptedFlights())->confirmStartUpNow($alphaFlight->id);
            $this->fail('Expected cross-operator start-up update to be denied.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertNull($alphaFlight->refresh()->time_start_up);
    }

    public function test_dispatch_cannot_accept_or_reject_any_flight_plan(): void
    {
        [$leadingEdge] = $this->operators();
        $dispatch = $this->user(UserRole::Dispatch, ['operator_id' => $leadingEdge->id]);
        $pendingFlight = $this->flight($leadingEdge, ['status' => FlightPlanStatus::Pending]);

        $this->actingAs($dispatch)
            ->post(route('flights.accept', $pendingFlight))
            ->assertForbidden();

        $this->actingAs($dispatch)
            ->post(route('flights.reject', $pendingFlight), ['rejection_reason' => 'No'])
            ->assertForbidden();

        $this->assertSame(FlightPlanStatus::Pending, $pendingFlight->refresh()->status);
    }

    public function test_authorized_atmo_admin_and_artisan_access_still_works(): void
    {
        [, $alpha] = $this->operators();
        $flight = $this->flight($alpha, ['status' => FlightPlanStatus::Accepted]);

        foreach ([
            [UserRole::Atmo, ['station' => 'RPUS']],
            [UserRole::Admin, []],
            [UserRole::Artisan, []],
        ] as [$role, $attributes]) {
            $user = $this->user($role, $attributes);

            $this->assertTrue($user->can('view', $flight));
            $this->assertTrue($user->can('update', $flight));
        }
    }

    public function test_navigation_badge_counts_only_visible_operator_records(): void
    {
        [$leadingEdge, $alpha] = $this->operators();
        $dispatch = $this->user(UserRole::Dispatch, ['operator_id' => $leadingEdge->id]);

        $this->flight($leadingEdge, [
            'aircraft_identification' => 'LEAD-PENDING',
            'status' => FlightPlanStatus::Pending,
            'reviewed_at' => null,
        ]);
        $this->flight($alpha, [
            'aircraft_identification' => 'ALPHA-PENDING',
            'status' => FlightPlanStatus::Pending,
            'reviewed_at' => null,
        ]);

        $this->actingAs($dispatch);

        $this->assertSame('New 1', FlightResource::getNavigationBadge());
    }

    public function test_dispatch_active_and_landed_queries_are_operator_scoped(): void
    {
        [$leadingEdge, $alpha] = $this->operators();
        $dispatch = $this->user(UserRole::Dispatch, ['operator_id' => $leadingEdge->id]);
        $leadingActive = $this->flight($leadingEdge, [
            'status' => FlightPlanStatus::Accepted,
            'time_start_up' => '08:10',
        ]);
        $alphaActive = $this->flight($alpha, [
            'status' => FlightPlanStatus::Accepted,
            'time_start_up' => '08:15',
        ]);
        $leadingLanded = $this->flight($leadingEdge, [
            'status' => FlightPlanStatus::Accepted,
            'time_start_up' => '08:10',
            'time_airborne' => '08:30',
            'time_touchdown' => '09:10',
        ]);
        $alphaLanded = $this->flight($alpha, [
            'status' => FlightPlanStatus::Accepted,
            'time_start_up' => '08:15',
            'time_airborne' => '08:35',
            'time_touchdown' => '09:15',
        ]);

        $this->actingAs($dispatch);

        $this->assertTrue(ActiveFlightResource::getEloquentQuery()->whereKey($leadingActive)->exists());
        $this->assertFalse(ActiveFlightResource::getEloquentQuery()->whereKey($alphaActive)->exists());

        Livewire::actingAs($dispatch)
            ->test(ListLandedFlights::class)
            ->assertSee((string) $leadingLanded->aircraft_identification)
            ->assertDontSee((string) $alphaLanded->aircraft_identification);
    }

    /**
     * @return array{0: Operator, 1: Operator}
     */
    private function operators(): array
    {
        return [
            Operator::factory()->create(['name' => 'Leading Edge', 'short_name' => 'LED']),
            Operator::factory()->create(['name' => 'Alpha Aviation', 'short_name' => 'ALP']),
        ];
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
    private function flight(Operator $operator, array $attributes = []): Flight
    {
        return Flight::create([
            'operator_id' => $operator->id,
            'status' => FlightPlanStatus::Accepted,
            'date_of_flight' => now('Asia/Manila')->addDay()->toDateString(),
            'proposed_time' => '1430',
            'aircraft_identification' => 'RPC'.fake()->unique()->numberBetween(100, 999),
            'departure_aerodrome' => 'RPUS',
            'destination_aerodrome' => 'RPLL',
            'route' => 'DCT',
            ...$attributes,
        ]);
    }
}
