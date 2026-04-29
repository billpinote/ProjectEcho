<?php

namespace Tests\Unit;

use App\Models\Flight;
use App\Services\FlightPlanQrPayloadService;
use Tests\TestCase;

class FlightPlanQrPayloadServiceTest extends TestCase
{
    public function test_it_builds_and_parses_a_signed_v2_offline_payload(): void
    {
        $flight = new Flight([
            'date_of_filing' => '2026-04-28',
            'date_of_flight' => '2026-04-29',
            'originator' => 'RPLLYFYX',
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
            'altn_aerodrome_1' => 'RPUA',
            'altn_aerodrome_2' => null,
            'other_information' => 'DOF/20260429 RMK/TEST',
            'endurance' => '05:00',
            'persons_on_board' => 4,
            'emergency_radio_uhf' => true,
            'emergency_radio_vhf' => true,
            'emergency_radio_elt' => false,
            'survival_equipment_polar' => false,
            'survival_equipment_desert' => false,
            'survival_equipment_maritime' => true,
            'survival_equipment_jungle' => false,
            'jackets_light' => true,
            'jackets_fluores' => true,
            'jackets_uhf' => false,
            'jackets_vhf' => true,
            'dinghies_enabled' => true,
            'dinghies_number' => 1,
            'dinghies_capacity' => 4,
            'dinghies_cover' => 'Y',
            'dinghies_color' => 'ORANGE',
            'aircraft_colour_and_markings' => 'WHITE BLUE',
            'remarks' => 'NIL',
            'pilot_in_command' => 'JUAN DELA CRUZ',
            'pilot_license_no' => 'LIC12345',
            'pilot_ratings' => 'PPL',
            'license_expiry_date' => '2027-01-01',
            'authorized_representative_enabled' => false,
            'authorized_representative_name' => null,
            'authorized_representative_role' => null,
            'authorized_representative_id_license' => null,
            'authorized_representative_expiry_date' => null,
        ]);
        $flight->id = 123;
        $flight->exists = true;

        $service = app(FlightPlanQrPayloadService::class);
        $payload = $service->buildPayload($flight);

        $this->assertIsString($payload);
        $this->assertStringStartsWith('ECHOFPL|2|OFFLINE|K1|S1|123|', $payload);

        $parsed = $service->parsePayload($payload);

        $this->assertIsArray($parsed);
        $this->assertSame('v2-offline', $parsed['format']);
        $this->assertSame(123, $parsed['flight_id']);
        $this->assertSame('RPC1234', $parsed['snapshot']['aircraft_identification']);
        $this->assertSame('DCT ABC DCT', $parsed['snapshot']['route']);
        $this->assertSame(4, $parsed['snapshot']['persons_on_board']);
        $this->assertTrue($parsed['snapshot']['emergency_radio_uhf']);
        $this->assertFalse($parsed['snapshot']['authorized_representative_enabled']);
    }
}
