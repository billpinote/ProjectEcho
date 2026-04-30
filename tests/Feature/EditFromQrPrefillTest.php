<?php

namespace Tests\Feature;

use App\Models\Flight;
use App\Services\FlightPlanQrPayloadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;
use Tests\TestCase;

class EditFromQrPrefillTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('aircraft_types_wtc')) {
            Schema::create('aircraft_types_wtc', function (Blueprint $table): void {
                $table->id();
                $table->string('icao_legacy_wtc')->nullable();
                $table->string('icao_type_designator')->nullable();
            });
        }
    }

    public function test_edit_from_qr_prefills_flight_rules_and_type_of_flight_selects(): void
    {
        $flight = Flight::create([
            'date_of_filing' => '2026-04-28',
            'date_of_flight' => '2026-04-29',
            'aircraft_identification' => 'RPC1234',
            'flight_rules' => 'V',
            'type_of_flight' => 'G',
            'number' => '1',
            'type_of_aircraft' => 'C172',
            'wake_turbulence_cat' => 'L',
            'equipment_10a' => 'SDFGIRWY',
            'equipment_10b' => 'C',
            'departure_aerodrome' => 'RPLL',
            'proposed_time' => '14:30',
            'cruising_speed' => 'N0120',
            'level' => 'F085',
            'route' => 'DCT ABC DCT',
            'destination_aerodrome' => 'RPLC',
            'total_eet' => '01:30',
            'endurance' => '05:00',
            'persons_on_board' => 4,
        ]);

        $payload = app(FlightPlanQrPayloadService::class)->buildPayload($flight);

        $response = $this->post(route('flightplan.edit-from-qr'), [
            'payload' => $payload,
        ]);

        $response
            ->assertRedirect(route('flightplan'))
            ->assertSessionHas('_old_input.flight_rules', 'V')
            ->assertSessionHas('_old_input.type_of_flight', 'G');

        $followUpResponse = $this->get(route('flightplan'));

        $followUpResponse
            ->assertOk()
            ->assertSee('<option value="V" selected>V</option>', false)
            ->assertSee('<option value="G" selected>G</option>', false);
    }

    public function test_edit_from_qr_prefills_inverted_checkbox_groups_consistently(): void
    {
        $flight = Flight::create([
            'date_of_filing' => '2026-04-28',
            'date_of_flight' => '2026-04-29',
            'aircraft_identification' => 'RPC5678',
            'flight_rules' => 'I',
            'type_of_flight' => 'S',
            'number' => '1',
            'type_of_aircraft' => 'C172',
            'wake_turbulence_cat' => 'L',
            'equipment_10a' => 'SDFGIRWY',
            'equipment_10b' => 'C',
            'departure_aerodrome' => 'RPLL',
            'proposed_time' => '14:30',
            'cruising_speed' => 'N0120',
            'level' => 'F085',
            'route' => 'DCT ABC DCT',
            'destination_aerodrome' => 'RPLC',
            'total_eet' => '01:30',
            'endurance' => '05:00',
            'persons_on_board' => 4,
            'emergency_radio_uhf' => true,
            'emergency_radio_vhf' => false,
            'emergency_radio_elt' => true,
            'survival_equipment_polar' => false,
            'survival_equipment_desert' => true,
            'survival_equipment_maritime' => false,
            'survival_equipment_jungle' => true,
            'jackets_light' => true,
            'jackets_fluores' => false,
            'jackets_uhf' => true,
            'jackets_vhf' => false,
        ]);

        $payload = app(FlightPlanQrPayloadService::class)->buildPayload($flight);

        $this->post(route('flightplan.edit-from-qr'), [
            'payload' => $payload,
        ])->assertRedirect(route('flightplan'));

        $response = $this->get(route('flightplan'));

        $response
            ->assertOk()
            ->assertSee('name="emergency_radio_vhf" value="0" class="inverted-checkbox" checked', false)
            ->assertDontSee('name="emergency_radio_uhf" value="0" class="inverted-checkbox" checked', false)
            ->assertSee('name="survival_equipment_polar" value="0" class="inverted-checkbox" checked', false)
            ->assertDontSee('name="survival_equipment_desert" value="0" class="inverted-checkbox" checked', false)
            ->assertSee('name="jackets_fluores" value="0" class="inverted-checkbox" checked', false)
            ->assertDontSee('name="jackets_light" value="0" class="inverted-checkbox" checked', false);
    }

    public function test_scanned_preview_shows_other_information_from_snapshot(): void
    {
        $token = (string) Str::uuid();

        $snapshot = [
            'aircraft_identification' => 'RPC9999',
            'flight_rules' => 'V',
            'type_of_flight' => 'G',
            'departure_aerodrome' => 'RPLL',
            'destination_aerodrome' => 'RPLC',
            'date_of_flight' => '2026-04-29',
            'proposed_time' => '14:30',
            'other_information' => 'DOF/20260429 RMK/TEST REMARK DEP/MANILA BAY',
        ];

        $response = $this
            ->withSession([
                'scanned_flight_plan_previews' => [
                    $token => [
                        'payload' => 'test-payload',
                        'snapshot' => $snapshot,
                    ],
                ],
            ])
            ->get(route('flightplan.scan-qr.preview', ['token' => $token]));

        $response
            ->assertOk()
            ->assertSee('RMK/TEST REMARK', false)
            ->assertSee('DEP/MANILA BAY', false);
    }
}
