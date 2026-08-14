<?php

namespace Tests\Feature;

use App\Domain\FlightPlans\Enums\FlightPlanStatus;
use App\Domain\Users\Enums\UserRole;
use App\Filament\Shared\Resources\Users\Pages\CreateUser;
use App\Models\Flight;
use App\Models\Operator;
use App\Models\User;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class OperatorStaffSupportTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_staff_role_normalizes_and_labels_correctly(): void
    {
        $this->assertSame(UserRole::OperatorStaff, UserRole::normalize('operator_staff'));
        $this->assertSame(UserRole::OperatorStaff, UserRole::normalize(' OPERATOR_STAFF '));
        $this->assertSame('Operator Staff', UserRole::OperatorStaff->label());
    }

    public function test_operator_staff_can_access_dispatch_panel_for_echo_preparation(): void
    {
        $staff = new User([
            'is_active' => true,
            'role' => UserRole::OperatorStaff,
        ]);

        $this->assertTrue($staff->canAccessPanel(Panel::make()->id('dispatch')));

        foreach (['artisan', 'admin', 'pilot', 'atmo', 'avsec', 'ats'] as $panelId) {
            $this->assertFalse($staff->canAccessPanel(Panel::make()->id($panelId)));
        }
    }

    public function test_operator_staff_can_create_but_cannot_review_or_fully_manage_flight_plans(): void
    {
        $operator = Operator::factory()->create();
        $staff = $this->user(UserRole::OperatorStaff, ['operator_id' => $operator->id]);
        $flight = $this->flight([
            'operator_id' => $operator->id,
            'prepared_by_user_id' => $staff->id,
        ]);

        $this->assertTrue($staff->canViewFlightPlans());
        $this->assertTrue($staff->canCreateFlightPlans());
        $this->assertFalse($staff->hasFullFlightAccess());
        $this->assertFalse($staff->canReviewFlightPlans());
        $this->assertFalse($staff->canUpdateFlightPlans());
        $this->assertFalse($staff->canUpdateFlightStartUpTime());
        $this->assertFalse($staff->can('accept', $flight));
        $this->assertFalse($staff->can('reject', $flight));
    }

    public function test_operator_staff_can_access_create_page_without_pending_atc_list(): void
    {
        $staff = $this->user(UserRole::OperatorStaff);

        $this->assertTrue(Route::has('filament.dispatch.resources.flights.create'));
        $this->assertFalse(Route::has('filament.dispatch.resources.flights.index'));

        $this->actingAs($staff)
            ->get('/dispatch')
            ->assertOk()
            ->assertSeeText('Create Flight Plan')
            ->assertDontSeeText('Pending Flight Plans');

        $this->actingAs($staff)
            ->get(route('filament.dispatch.resources.flights.create'))
            ->assertOk();

        $this->actingAs($staff)
            ->get('/dispatch/flights')
            ->assertNotFound();
    }

    public function test_operator_staff_profile_relationships_resolve_user_and_operator(): void
    {
        $operator = Operator::factory()->create();
        $staff = $this->user(UserRole::OperatorStaff, ['operator_id' => $operator->id]);
        $profile = $staff->operatorStaffProfile()->create([
            'operator_id' => $operator->id,
            'position_title' => 'Aircraft Mechanic',
            'company_employee_id' => 'M-100',
            'authorization_reference' => 'OPS-AUTH-1',
            'authorization_expiry_date' => '2026-12-31',
            'is_authorized' => true,
        ]);

        $this->assertTrue($staff->operatorStaffProfile->is($profile));
        $this->assertTrue($profile->user->is($staff));
        $this->assertTrue($profile->operator->is($operator));
        $this->assertTrue($operator->operatorStaffProfiles()->whereKey($profile)->exists());
    }

    public function test_admin_can_create_operator_staff_profile(): void
    {
        $admin = $this->user(UserRole::Admin);
        $operator = Operator::factory()->create(['name' => 'Canonical Air']);

        Livewire::actingAs($admin)
            ->test(CreateUser::class)
            ->fillForm([
                'first_name' => 'Pedro',
                'last_name' => 'Santos',
                'email' => 'pedro.operator@example.test',
                'role' => UserRole::OperatorStaff->value,
                'operator_id' => $operator->id,
                'password' => 'StrongPass123!',
                'operator_staff_position_title' => 'Aircraft Mechanic',
                'operator_staff_company_employee_id' => 'M-100',
                'operator_staff_authorization_reference' => 'OPS-AUTH-1',
                'operator_staff_authorization_expiry_date' => '2026-12-31',
                'operator_staff_is_authorized' => true,
                'operator_staff_remarks' => 'Line maintenance.',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $staff = User::where('email', 'pedro.operator@example.test')->firstOrFail();

        $this->assertSame(UserRole::OperatorStaff, $staff->role);
        $this->assertSame($operator->id, $staff->operator_id);
        $this->assertDatabaseHas('operator_staff_profiles', [
            'user_id' => $staff->id,
            'operator_id' => $operator->id,
            'position_title' => 'Aircraft Mechanic',
            'company_employee_id' => 'M-100',
            'authorization_reference' => 'OPS-AUTH-1',
            'is_authorized' => true,
        ]);
    }

    public function test_operator_staff_prepared_flight_records_preparer_position_snapshot(): void
    {
        Storage::fake('public');

        $operator = Operator::factory()->create(['name' => 'Canonical Air', 'short_name' => 'CAN']);
        $staff = $this->user(UserRole::OperatorStaff, [
            'first_name' => 'Pedro',
            'middle_name' => null,
            'last_name' => 'Santos',
            'suffix' => null,
            'operator_id' => $operator->id,
        ]);
        $staff->operatorStaffProfile()->create([
            'operator_id' => $operator->id,
            'position_title' => 'Aircraft Mechanic',
        ]);

        $this->actingAs($staff)
            ->withSession(['flight_plan_preview' => $this->previewFlightPlanData([
                'departure_aerodrome' => 'RPUS',
            ])])
            ->post(route('flightplan.approve'))
            ->assertRedirect();

        $flight = Flight::latest('id')->firstOrFail();

        $this->assertSame($staff->id, $flight->prepared_by_user_id);
        $this->assertSame('PEDRO SANTOS', $flight->prepared_by_name);
        $this->assertSame('AIRCRAFT MECHANIC', $flight->prepared_by_role);
        $this->assertSame($operator->id, $flight->operator_id);
        $this->assertTrue($flight->requiresPicAuthorization());
        $this->assertFalse($flight->canSubmitToAtc());
    }

    public function test_operator_staff_falls_back_to_operator_staff_role_snapshot(): void
    {
        Storage::fake('public');

        $staff = $this->user(UserRole::OperatorStaff, [
            'first_name' => 'Olivia',
            'last_name' => 'Ops',
            'operator_id' => Operator::factory()->create()->id,
        ]);

        $this->actingAs($staff)
            ->withSession(['flight_plan_preview' => $this->previewFlightPlanData([
                'departure_aerodrome' => 'RPUS',
            ])])
            ->post(route('flightplan.approve'))
            ->assertRedirect();

        $this->assertSame('Operator Staff', Flight::latest('id')->firstOrFail()->prepared_by_role);
    }

    public function test_operator_staff_cannot_bypass_pic_authorization_even_if_pic_user_matches(): void
    {
        $staff = $this->user(UserRole::OperatorStaff);
        $flight = $this->flight([
            'prepared_by_user_id' => $staff->id,
            'pilot_in_command_user_id' => $staff->id,
        ]);

        $this->assertTrue($flight->requiresPicAuthorization());
        $this->assertFalse($flight->canSubmitToAtc());
    }

    public function test_pilot_and_dispatch_permission_behavior_remains_unchanged(): void
    {
        $pilot = $this->user(UserRole::Pilot);
        $dispatch = $this->user(UserRole::Dispatch);

        $this->assertTrue($pilot->canCreateFlightPlans());
        $this->assertFalse($pilot->hasFullFlightAccess());
        $this->assertFalse($pilot->canReviewFlightPlans());

        $this->assertTrue($dispatch->canCreateFlightPlans());
        $this->assertFalse($dispatch->hasFullFlightAccess());
        $this->assertFalse($dispatch->canReviewFlightPlans());
        $this->assertTrue($dispatch->canUpdateFlightStartUpTime());
    }

    private function user(UserRole $role, array $attributes = []): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
            ...$attributes,
        ]);
    }

    private function flight(array $attributes = []): Flight
    {
        return Flight::create([
            'status' => FlightPlanStatus::Pending,
            'date_of_flight' => now('Asia/Manila')->addDay()->toDateString(),
            'proposed_time' => '1430',
            'aircraft_identification' => 'OPS001',
            'departure_aerodrome' => 'RPUS',
            'destination_aerodrome' => 'RPLL',
            'route' => 'DCT',
            ...$attributes,
        ]);
    }

    private function previewFlightPlanData(array $overrides = []): array
    {
        $date = now('UTC')->addDay();

        return [
            'date_of_filing' => now('UTC')->toDateString(),
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
            'proposed_time' => '14:30',
            'cruising_speed' => 'N450',
            'level' => 'F350',
            'route' => 'DCT',
            'destination_aerodrome' => 'RPLL',
            'total_eet' => '02:30',
            'endurance' => '04:00',
            'persons_on_board' => 2,
            'other_information' => 'DOF/'.$date->format('Ymd'),
            'pilot_in_command' => 'CAPTAIN TEST',
            'pilot_license_no' => 'LIC123',
            'pilot_ratings' => 'IR',
            'license_expiry_date' => $date->addYear()->toDateString(),
            'dinghies_enabled' => false,
            'authorized_representative_enabled' => false,
            ...$overrides,
        ];
    }
}
