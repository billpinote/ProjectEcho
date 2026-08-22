<?php

namespace Tests\Feature;

use App\Domain\FlightPlans\Enums\FlightPlanStatus;
use App\Domain\FlightPlans\Support\FlightPlanPreparerContext;
use App\Domain\Pilots\Enums\PilotLicenseType;
use App\Domain\Pilots\Enums\PilotQualificationCategory;
use App\Domain\Users\Enums\UserRole;
use App\Filament\Panels\Dispatch\Resources\Flights\Pages\CreateFlight as DispatchCreateFlight;
use App\Filament\Panels\Dispatch\Resources\AwaitingAuthorizationFlights\AwaitingAuthorizationFlightResource as DispatchAwaitingAuthorizationFlightResource;
use App\Filament\Panels\Pilot\Resources\AwaitingAuthorizationFlights\AwaitingAuthorizationFlightResource;
use App\Filament\Panels\Pilot\Resources\MyCurrentFlights\MyCurrentFlightResource;
use App\Filament\Shared\Resources\Flights\Pages\CreateFlight;
use App\Models\Flight;
use App\Models\Operator;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FlightPlanPreparerContextFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_staff_automatically_prepares_for_another_pic_with_locked_representative_fields(): void
    {
        Filament::setCurrentPanel('dispatch');

        $operator = Operator::factory()->create();
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
            'company_employee_id' => 'AAG-MECH-001',
            'authorization_reference' => 'AAG-FPL-REP-001',
            'authorization_expiry_date' => '2028-12-31',
        ]);

        Livewire::actingAs($staff)
            ->test(DispatchCreateFlight::class)
            ->assertSee('Awaiting PIC identification. Verified PIC credentials will be completed during PIC authorization.')
            ->assertSee('data-caap-pending-pic-dismiss', false)
            ->assertFormSet([
                'authorized_representative_enabled' => true,
                'authorized_representative_name' => 'PEDRO SANTOS',
                'authorized_representative_role' => 'AIRCRAFT MECHANIC',
                'authorized_representative_id_license' => 'AAG-MECH-001',
                'authorized_representative_expiry_date' => '2028-12-31',
                'pilot_in_command' => null,
                'pilot_license_no' => null,
                'pilot_ratings' => null,
                'license_expiry_date' => null,
            ])
            ->assertFormFieldDisabled('authorized_representative_enabled')
            ->assertFormFieldReadOnly('authorized_representative_name')
            ->assertFormFieldReadOnly('authorized_representative_role')
            ->assertFormFieldReadOnly('authorized_representative_id_license')
            ->assertFormFieldReadOnly('pilot_license_no')
            ->assertFormFieldReadOnly('pilot_ratings')
            ->assertFormFieldReadOnly('license_expiry_date');
    }

    public function test_dispatch_automatically_prepares_for_another_pic_with_representative_identity(): void
    {
        Filament::setCurrentPanel('dispatch');

        $dispatch = $this->user(UserRole::Dispatch, [
            'first_name' => 'Ava',
            'middle_name' => null,
            'last_name' => 'Mendoza',
            'suffix' => null,
            'employee_id' => 'DISP-EMP-1',
        ]);
        $dispatch->dispatchProfile()->create([
            'dispatcher_license_number' => 'DISP-LIC-123',
            'dispatcher_certificate' => 'DISP-CERT-456',
            'position' => 'Flight Dispatcher',
            'department' => 'Operations Control',
        ]);

        Livewire::actingAs($dispatch)
            ->test(DispatchCreateFlight::class)
            ->assertFormSet([
                'authorized_representative_enabled' => true,
                'authorized_representative_name' => 'AVA MENDOZA',
                'authorized_representative_role' => 'FLIGHT DISPATCHER',
                'authorized_representative_id_license' => 'DISP-LIC-123',
                'pilot_in_command' => null,
                'pilot_license_no' => null,
                'pilot_ratings' => null,
                'license_expiry_date' => null,
            ])
            ->assertFormFieldDisabled('authorized_representative_enabled')
            ->assertFormFieldReadOnly('authorized_representative_name')
            ->assertFormFieldReadOnly('pilot_license_no');
    }

    public function test_pilot_self_pic_retains_existing_qualification_autofill(): void
    {
        $pilot = $this->pilotWithCredentials();

        Livewire::actingAs($pilot)
            ->test(CreateFlight::class)
            ->assertDontSee('Filing Capacity')
            ->assertSee('Not the PIC for this flight?')
            ->assertDontSee('Awaiting PIC identification. Verified PIC credentials will be completed during PIC authorization.')
            ->assertFormSet([
                'filing_capacity' => 'self_pic',
                'pilot_in_command' => 'VERIFIED PILOT',
                'pilot_license_no' => 'CPL-123456',
                'pilot_ratings' => 'C172',
                'license_expiry_date' => '2028-12-31',
                'authorized_representative_enabled' => false,
                'authorized_representative_name' => null,
                'authorized_representative_role' => null,
                'authorized_representative_id_license' => null,
                'authorized_representative_expiry_date' => null,
            ])
            ->assertFormFieldReadOnly('pilot_license_no')
            ->assertFormFieldEnabled('authorized_representative_enabled');
    }

    public function test_pilot_self_pic_save_stores_only_pic_certification_data(): void
    {
        $pilot = $this->pilotWithCredentials();

        Livewire::actingAs($pilot)
            ->test(CreateFlight::class)
            ->fillForm($this->validFlightPlanFormData([
                'authorized_representative_enabled' => true,
                'authorized_representative_name' => 'FORGED REPRESENTATIVE',
                'authorized_representative_role' => 'PILOT',
                'authorized_representative_id_license' => 'CPL-FORGED',
                'authorized_representative_expiry_date' => '2035-01-01',
            ]))
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(MyCurrentFlightResource::getUrl('index', panel: 'pilot'));

        $flight = Flight::query()->latest('id')->firstOrFail();

        $this->assertSame('VERIFIED PILOT', $flight->pilot_in_command);
        $this->assertSame('CPL-123456', $flight->pilot_license_no);
        $this->assertFalse($flight->authorized_representative_enabled);
        $this->assertNull($flight->authorized_representative_name);
        $this->assertNull($flight->authorized_representative_role);
        $this->assertNull($flight->authorized_representative_id_license);
        $this->assertNull($flight->authorized_representative_expiry_date);
    }

    public function test_ppl_cpl_and_atpl_forms_hide_capacity_dropdown_and_show_secondary_pic_action(): void
    {
        foreach ([
            PilotLicenseType::PrivatePilot,
            PilotLicenseType::CommercialPilot,
            PilotLicenseType::AirlineTransportPilot,
        ] as $licenseType) {
            $pilot = $this->pilotWithCredentials([], $licenseType);

            Livewire::actingAs($pilot)
                ->test(CreateFlight::class)
                ->assertDontSee('Filing Capacity')
                ->assertSee('Not the PIC for this flight?')
                ->assertFormSet([
                    'filing_capacity' => FlightPlanPreparerContext::CAPACITY_SELF_PIC,
                ]);
        }
    }

    public function test_licensed_pilot_can_switch_to_another_pic_and_back_without_losing_verified_credentials(): void
    {
        $pilot = $this->pilotWithCredentials();

        Livewire::actingAs($pilot)
            ->test(CreateFlight::class)
            ->callFormComponentAction('pilot-pic-capacity-actions', 'prepareForAnotherPic')
            ->assertSee('Awaiting PIC identification. Verified PIC credentials will be completed during PIC authorization.')
            ->assertFormSet([
                'filing_capacity' => FlightPlanPreparerContext::CAPACITY_FOR_ANOTHER_PIC,
                'authorized_representative_enabled' => true,
                'authorized_representative_name' => 'VERIFIED PILOT',
                'authorized_representative_role' => 'PILOT',
                'authorized_representative_id_license' => 'CPL-123456',
                'pilot_in_command' => null,
                'pilot_license_no' => null,
                'pilot_ratings' => null,
                'license_expiry_date' => null,
            ])
            ->assertSee('I am the PIC')
            ->callFormComponentAction('pilot-pic-capacity-actions', 'prepareAsSelfPic')
            ->assertDontSee('Awaiting PIC identification. Verified PIC credentials will be completed during PIC authorization.')
            ->assertFormSet([
                'filing_capacity' => FlightPlanPreparerContext::CAPACITY_SELF_PIC,
                'authorized_representative_enabled' => false,
                'authorized_representative_name' => null,
                'authorized_representative_role' => null,
                'authorized_representative_id_license' => null,
                'authorized_representative_expiry_date' => null,
                'pilot_in_command' => 'VERIFIED PILOT',
                'pilot_license_no' => 'CPL-123456',
                'pilot_ratings' => 'C172',
                'license_expiry_date' => '2028-12-31',
            ])
            ->assertSee('Not the PIC for this flight?');
    }

    public function test_pilot_capacity_toggle_preserves_unrelated_flight_plan_state(): void
    {
        $pilot = $this->pilotWithCredentials();
        $unrelatedFields = [
            'emergency_radio_uhf' => true,
            'emergency_radio_vhf' => false,
            'emergency_radio_elt' => true,
            'survival_equipment_polar' => false,
            'survival_equipment_desert' => true,
            'survival_equipment_maritime' => false,
            'survival_equipment_jungle' => true,
            'jackets_light' => false,
            'jackets_fluores' => true,
            'jackets_uhf' => false,
            'jackets_vhf' => true,
            'dinghies_enabled' => false,
            'dinghies_number' => '2',
            'dinghies_capacity' => '4',
            'dinghies_cover' => 'Y',
            'dinghies_color' => 'ORANGE',
            'endurance' => '0400',
            'persons_on_board' => '3',
            'aircraft_colour_and_markings' => 'WHITE BLUE',
            'remarks' => 'PRESERVE THIS REMARK',
        ];

        $component = Livewire::actingAs($pilot)
            ->test(CreateFlight::class)
            ->fillForm($unrelatedFields);
        $before = $component->get('data');

        $component->callFormComponentAction('pilot-pic-capacity-actions', 'prepareForAnotherPic');
        $forAnotherPic = $component->get('data');

        foreach (array_keys($unrelatedFields) as $field) {
            $this->assertSame($before[$field] ?? null, $forAnotherPic[$field] ?? null, $field.' changed while preparing for another PIC.');
        }
        $this->assertSame(FlightPlanPreparerContext::CAPACITY_FOR_ANOTHER_PIC, $forAnotherPic['filing_capacity']);

        $component->callFormComponentAction('pilot-pic-capacity-actions', 'prepareAsSelfPic');
        $selfPic = $component->get('data');

        foreach (array_keys($unrelatedFields) as $field) {
            $this->assertSame($before[$field] ?? null, $selfPic[$field] ?? null, $field.' changed while returning to self PIC.');
        }
        $this->assertSame(FlightPlanPreparerContext::CAPACITY_SELF_PIC, $selfPic['filing_capacity']);
    }

    public function test_non_student_pilot_licenses_retain_self_pic_capacity_behavior(): void
    {
        foreach ([
            PilotLicenseType::PrivatePilot,
            PilotLicenseType::CommercialPilot,
            PilotLicenseType::AirlineTransportPilot,
        ] as $licenseType) {
            $pilot = $this->pilotWithCredentials();
            $pilot->pilotProfile()->update(['license_type' => $licenseType]);

            $context = FlightPlanPreparerContext::for($pilot, [
                'filing_capacity' => FlightPlanPreparerContext::CAPACITY_SELF_PIC,
            ]);

            $this->assertSame(FlightPlanPreparerContext::CAPACITY_SELF_PIC, $context->capacity());
            $this->assertTrue($context->preparerActsAsPic());
            $this->assertTrue($context->shouldShowFilingCapacityControl());
        }
    }

    public function test_spl_representative_license_is_formatted_during_context_and_form_hydration(): void
    {
        $pilot = $this->pilotWithCredentials([], PilotLicenseType::StudentPilot, '123456');
        $context = FlightPlanPreparerContext::for($pilot);

        $this->assertSame('SPL-123456', $context->representativeIdOrLicense());

        Livewire::actingAs($pilot)
            ->test(CreateFlight::class)
            ->fillForm(['filing_capacity' => FlightPlanPreparerContext::CAPACITY_FOR_ANOTHER_PIC])
            ->assertFormSet(['authorized_representative_id_license' => 'SPL-123456'])
            ->fillForm($this->validFlightPlanFormData([
                'pilot_in_command' => 'FORGED PIC',
                'pilot_license_no' => '123456',
            ]))
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(AwaitingAuthorizationFlightResource::getUrl('index', panel: 'pilot'));

        $flight = Flight::query()->latest('id')->firstOrFail();

        $this->assertSame('SPL-123456', $flight->authorized_representative_id_license);
    }

    public function test_pilot_can_choose_prepare_for_another_pic_and_their_credentials_are_not_used_as_pic(): void
    {
        $pilot = $this->pilotWithCredentials();

        Livewire::actingAs($pilot)
            ->test(CreateFlight::class)
            ->fillForm(['filing_capacity' => 'for_another_pic'])
            ->assertSee('Awaiting PIC identification. Verified PIC credentials will be completed during PIC authorization.')
            ->assertSee('data-caap-pending-pic-dismiss', false)
            ->assertFormSet([
                'authorized_representative_enabled' => true,
                'authorized_representative_name' => 'VERIFIED PILOT',
                'authorized_representative_role' => 'PILOT',
                'authorized_representative_id_license' => 'CPL-123456',
                'authorized_representative_expiry_date' => '2028-12-31',
                'pilot_in_command' => null,
                'pilot_license_no' => null,
                'pilot_ratings' => null,
                'license_expiry_date' => null,
            ])
            ->assertFormFieldDisabled('authorized_representative_enabled')
            ->assertFormFieldReadOnly('pilot_license_no');
    }

    public function test_pending_pic_alert_hides_after_pic_is_identified(): void
    {
        $pilot = $this->pilotWithCredentials();
        $identifiedPic = $this->pilotWithCredentials([
            'first_name' => 'Identified',
            'last_name' => 'Pic',
        ]);

        Livewire::actingAs($pilot)
            ->test(CreateFlight::class)
            ->fillForm(['filing_capacity' => 'for_another_pic'])
            ->assertSee('Awaiting PIC identification. Verified PIC credentials will be completed during PIC authorization.')
            ->fillForm(['pilot_in_command_user_id' => $identifiedPic->id])
            ->assertDontSee('Awaiting PIC identification. Verified PIC credentials will be completed during PIC authorization.');
    }

    public function test_pending_pic_alert_remains_condition_driven_across_fresh_renders(): void
    {
        Filament::setCurrentPanel('dispatch');

        $operator = Operator::factory()->create();
        $staff = $this->user(UserRole::OperatorStaff, [
            'operator_id' => $operator->id,
        ]);
        $staff->operatorStaffProfile()->create([
            'operator_id' => $operator->id,
        ]);

        Livewire::actingAs($staff)
            ->test(DispatchCreateFlight::class)
            ->assertSee('Awaiting PIC identification. Verified PIC credentials will be completed during PIC authorization.')
            ->assertSee('data-caap-pending-pic-dismiss', false)
            ->assertDontSee('localStorage', false)
            ->assertDontSee('sessionStorage', false);

        Livewire::actingAs($staff)
            ->test(DispatchCreateFlight::class)
            ->assertSee('Awaiting PIC identification. Verified PIC credentials will be completed during PIC authorization.')
            ->assertSee('data-caap-pending-pic-dismiss', false);
    }

    public function test_non_pic_preparer_create_records_snapshots_and_requires_pic_authorization(): void
    {
        Filament::setCurrentPanel('dispatch');

        $operator = Operator::factory()->create(['name' => 'Alpha Aviation Group', 'short_name' => 'AAG']);
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
            'company_employee_id' => 'AAG-MECH-001',
            'authorization_expiry_date' => '2028-12-31',
        ]);

        Livewire::actingAs($staff)
            ->test(DispatchCreateFlight::class)
            ->fillForm($this->validFlightPlanFormData([
                'pilot_in_command' => 'FORGED PIC',
                'pilot_license_no' => 'FORGED-LIC',
                'pilot_ratings' => 'FORGED',
                'license_expiry_date' => '2035-01-01',
            ]))
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(DispatchAwaitingAuthorizationFlightResource::getUrl('index', panel: 'dispatch'));

        $flight = Flight::latest('id')->firstOrFail();

        $this->assertSame(FlightPlanStatus::AwaitingPic, $flight->status);
        $this->assertSame($staff->id, $flight->prepared_by_user_id);
        $this->assertSame('PEDRO SANTOS', $flight->prepared_by_name);
        $this->assertSame('AIRCRAFT MECHANIC', $flight->prepared_by_role);
        $this->assertNull($flight->pilot_in_command_user_id);
        $this->assertNull($flight->pilot_in_command);
        $this->assertNull($flight->pilot_license_no);
        $this->assertNull($flight->pilot_ratings);
        $this->assertNull($flight->license_expiry_date);
        $this->assertTrue($flight->authorized_representative_enabled);
        $this->assertSame('PEDRO SANTOS', $flight->authorized_representative_name);
        $this->assertSame('AIRCRAFT MECHANIC', $flight->authorized_representative_role);
        $this->assertSame('AAG-MECH-001', $flight->authorized_representative_id_license);
        $this->assertTrue($flight->requiresPicAuthorization());
        $this->assertFalse($flight->canSubmitToAtc());
    }

    public function test_pilot_prepare_for_another_pic_create_does_not_store_pilot_as_pic(): void
    {
        $pilot = $this->pilotWithCredentials();

        Livewire::actingAs($pilot)
            ->test(CreateFlight::class)
            ->fillForm($this->validFlightPlanFormData([
                'filing_capacity' => 'for_another_pic',
                'pilot_in_command' => 'FORGED PIC',
                'pilot_license_no' => 'FORGED-LIC',
                'pilot_ratings' => 'FORGED',
                'license_expiry_date' => '2035-01-01',
            ]))
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(AwaitingAuthorizationFlightResource::getUrl('index', panel: 'pilot'));

        $flight = Flight::latest('id')->firstOrFail();

        $this->assertSame(FlightPlanStatus::AwaitingPic, $flight->status);
        $this->assertSame($pilot->id, $flight->prepared_by_user_id);
        $this->assertNull($flight->pilot_id);
        $this->assertNull($flight->pilot_in_command_user_id);
        $this->assertNull($flight->pilot_license_no);
        $this->assertTrue($flight->authorized_representative_enabled);
        $this->assertSame('VERIFIED PILOT', $flight->authorized_representative_name);
        $this->assertSame('CPL-123456', $flight->authorized_representative_id_license);
        $this->assertSame('2028-12-31', $flight->authorized_representative_expiry_date);
        $this->assertTrue($flight->requiresPicAuthorization());
        $this->assertFalse($flight->canSubmitToAtc());
    }

    private function user(UserRole $role, array $attributes = []): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
            ...$attributes,
        ]);
    }

    private function pilotWithCredentials(
        array $attributes = [],
        PilotLicenseType $licenseType = PilotLicenseType::CommercialPilot,
        string $licenseNumber = '123456',
    ): User {
        $pilot = $this->user(UserRole::Pilot, [
            'first_name' => 'Verified',
            'middle_name' => null,
            'last_name' => 'Pilot',
            'suffix' => null,
            ...$attributes,
        ]);
        $profile = $pilot->pilotProfile()->create([
            'license_type' => $licenseType,
            'license_number' => $licenseNumber,
            'license_expiry_date' => '2028-12-31',
        ]);
        $profile->qualifications()->create([
            'category' => PilotQualificationCategory::AircraftRating,
            'code' => 'C172',
        ]);

        return $pilot;
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
