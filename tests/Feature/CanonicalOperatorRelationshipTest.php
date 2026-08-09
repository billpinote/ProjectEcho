<?php

namespace Tests\Feature;

use App\Domain\FlightPlans\Enums\FlightPlanStatus;
use App\Domain\Users\Enums\UserRole;
use App\Filament\Panels\Admin\Resources\Users\Pages\CreateUser;
use App\Filament\Shared\Resources\Flights\Pages\CreateFlight;
use App\Models\Flight;
use App\Models\Operator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CanonicalOperatorRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_and_flights_belong_to_operator_and_operator_has_related_records(): void
    {
        $operator = Operator::factory()->create(['name' => 'Canonical Air', 'short_name' => 'CAN']);
        $user = $this->user(UserRole::Pilot, ['operator_id' => $operator->id]);
        $flight = $this->flight([
            'filed_by_user_id' => $user->id,
            'operator_id' => $operator->id,
        ]);

        $this->assertTrue($user->operator->is($operator));
        $this->assertTrue($flight->operator->is($operator));
        $this->assertTrue($operator->users()->whereKey($user)->exists());
        $this->assertTrue($operator->flights()->whereKey($flight)->exists());
    }

    public function test_operator_relationships_are_nullable_and_null_on_delete(): void
    {
        $operator = Operator::factory()->create();
        $user = $this->user(UserRole::Pilot, ['operator_id' => $operator->id]);
        $flight = $this->flight(['operator_id' => $operator->id]);

        $operator->delete();

        $this->assertNull($user->refresh()->operator_id);
        $this->assertNull($flight->refresh()->operator_id);

        $this->assertNull($this->user(UserRole::Pilot)->operator_id);
        $this->assertNull($this->flight()->operator_id);
    }

    public function test_admin_user_form_assigns_pilot_operator_without_touching_legacy_operator_text(): void
    {
        $admin = $this->user(UserRole::Admin);
        $operator = Operator::factory()->create(['name' => 'Profile Air', 'short_name' => 'PRF']);

        Livewire::actingAs($admin)
            ->test(CreateUser::class)
            ->fillForm([
                'first_name' => 'Profile',
                'last_name' => 'Pilot',
                'email' => 'profile-pilot@example.test',
                'role' => UserRole::Pilot->value,
                'operator_id' => $operator->id,
                'password' => 'StrongPass123!',
                'pilot_license_number' => 'PILOT-1',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $pilot = User::where('email', 'profile-pilot@example.test')->firstOrFail();

        $this->assertSame($operator->id, $pilot->operator_id);
        $this->assertNull($pilot->pilotProfile?->operator);
    }

    public function test_admin_user_form_assigns_dispatch_operator(): void
    {
        $admin = $this->user(UserRole::Admin);
        $operator = Operator::factory()->create(['name' => 'Dispatch Air', 'short_name' => 'DSP']);

        Livewire::actingAs($admin)
            ->test(CreateUser::class)
            ->fillForm([
                'first_name' => 'Profile',
                'last_name' => 'Dispatcher',
                'email' => 'profile-dispatch@example.test',
                'role' => UserRole::Dispatch->value,
                'operator_id' => $operator->id,
                'password' => 'StrongPass123!',
                'dispatch_dispatcher_license_number' => 'DISP-1',
                'dispatch_dispatcher_certificate' => 'CERT-1',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $dispatch = User::where('email', 'profile-dispatch@example.test')->firstOrFail();

        $this->assertSame($operator->id, $dispatch->refresh()->operator_id);
    }

    public function test_pilot_created_flight_uses_canonical_operator_and_ignores_browser_operator_values(): void
    {
        $operator = Operator::factory()->create(['name' => 'Canonical Air', 'short_name' => 'CAN']);
        $pilot = $this->user(UserRole::Pilot, [
            'first_name' => 'Test',
            'last_name' => 'Pilot',
            'operator_id' => $operator->id,
        ]);
        $pilot->pilotProfile()->create([
            'license_number' => 'LIC-123',
            'ratings' => 'IR',
            'operator' => 'LEGACY',
        ]);

        Livewire::actingAs($pilot)
            ->test(CreateFlight::class)
            ->fillForm($this->validFlightPlanFormData([
                'operator_id' => Operator::factory()->create()->id,
                'other_information' => 'DOF/'.now('Asia/Manila')->addDay()->format('Ymd').' OPR/FAKE',
            ]))
            ->call('create')
            ->assertHasNoFormErrors();

        $flight = Flight::latest('id')->firstOrFail();

        $this->assertSame($operator->id, $flight->operator_id);
        $this->assertSame('CAN', $flight->other_info_opr);
        $this->assertStringNotContainsString('OPR/FAKE', $flight->other_information);
    }

    public function test_dispatch_created_flight_uses_canonical_operator(): void
    {
        $operator = Operator::factory()->create(['name' => 'Dispatch Air', 'short_name' => 'DSP']);
        $dispatch = $this->user(UserRole::Dispatch, ['operator_id' => $operator->id]);

        $this->actingAs($dispatch)
            ->post(route('flightplan.store'), $this->validFlightPlanFormData([
                'other_information' => 'DOF/'.now('Asia/Manila')->addDay()->format('Ymd').' OPR/FAKE',
            ]))
            ->assertRedirect(route('flightplan.preview'));

        $this->actingAs($dispatch)
            ->post(route('flightplan.approve'))
            ->assertRedirect();

        $flight = Flight::latest('id')->firstOrFail();

        $this->assertSame($operator->id, $flight->operator_id);
        $this->assertSame('DSP', $flight->other_info_opr);
    }

    public function test_public_pdf_only_flow_keeps_operator_id_out_of_operational_records(): void
    {
        Storage::fake('public');

        $response = $this->post(route('flightplan.store'), $this->validFlightPlanFormData([
            'departure_aerodrome' => 'RPLL',
            'other_information' => 'DOF/'.now('Asia/Manila')->addDay()->format('Ymd').' OPR/Public Operator',
            'operator_id' => Operator::factory()->create()->id,
        ]));

        $response->assertRedirect(route('flightplan.preview'));

        $this->post(route('flightplan.pdf-only'))
            ->assertOk();

        $this->assertDatabaseCount('flights', 0);
        $this->assertEmpty(Storage::disk('public')->allFiles('flight-plans'));
    }

    public function test_legacy_pilot_profile_operator_backfill_matches_only_exact_unambiguous_operator_names(): void
    {
        $matchedOperator = Operator::factory()->create(['name' => 'Exact Match Air', 'short_name' => 'EMA']);
        Operator::factory()->create(['name' => 'Ambiguous One', 'short_name' => 'DUP']);
        Operator::factory()->create(['name' => 'Ambiguous Two', 'short_name' => 'DUP']);

        $matchedPilot = $this->user(UserRole::Pilot);
        $matchedPilot->pilotProfile()->create(['operator' => ' exact   match air ']);

        $shortNamePilot = $this->user(UserRole::Pilot);
        $shortNamePilot->pilotProfile()->create(['operator' => 'EMA']);

        $ambiguousPilot = $this->user(UserRole::Pilot);
        $ambiguousPilot->pilotProfile()->create(['operator' => 'DUP']);

        $unmatchedPilot = $this->user(UserRole::Pilot);
        $unmatchedPilot->pilotProfile()->create(['operator' => 'Missing Operator']);

        $migration = require database_path('migrations/2026_08_08_000001_add_canonical_operator_to_flights_and_backfill_users.php');
        $migration->up();

        $this->assertSame($matchedOperator->id, $matchedPilot->refresh()->operator_id);
        $this->assertSame($matchedOperator->id, $shortNamePilot->refresh()->operator_id);
        $this->assertNull($ambiguousPilot->refresh()->operator_id);
        $this->assertNull($unmatchedPilot->refresh()->operator_id);
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

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validFlightPlanFormData(array $overrides = []): array
    {
        $date = now('Asia/Manila')->addDay();

        return [
            'date_of_flight' => $date->toDateString(),
            'aircraft_identification' => 'N12345',
            'flight_rules' => 'I',
            'type_of_flight' => 'S',
            'number' => '1',
            'type_of_aircraft' => 'B747',
            'wake_turbulence_cat' => 'H',
            'equipment_10a' => 'S',
            'equipment_10b' => 'C',
            'departure_aerodrome' => 'RPUS',
            'proposed_time' => '1430',
            'cruising_speed' => 'N450',
            'level' => 'F350',
            'route' => 'DCT',
            'destination_aerodrome' => 'RPLL',
            'total_eet' => '0230',
            'endurance' => '0400',
            'persons_on_board' => '2',
            'other_information' => 'DOF/'.$date->format('Ymd'),
            'pilot_in_command' => 'TEST PILOT',
            'pilot_license_no' => 'LIC-123',
            'pilot_ratings' => 'IR',
            'license_expiry_date' => $date->copy()->addYear()->toDateString(),
            'dinghies_enabled' => false,
            'authorized_representative_enabled' => false,
            ...$overrides,
        ];
    }
}
