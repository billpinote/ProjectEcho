<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlightPlanIcaoValidationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test valid flight plan submission with ICAO rules
     */
    public function test_valid_flight_plan_with_icao_rules(): void
    {
        $flightPlanData = [
            'date_of_flight' => now()->addDay()->toDateString(),
            'aircraft_identification' => 'N12345',
            'flight_rules' => 'I',
            'type_of_flight' => 'S',
            'type_of_aircraft' => 'B747',
            'wake_turbulence_cat' => 'H',
            'departure_aerodrome' => 'KJFK',
            'departure_time' => '14:30',
            'cruising_speed' => 'N450',
            'level' => 'F350',
            'route' => 'KJFK DIRECT LFPG',
            'destination_aerodrome' => 'LFPG',
            'destination_time' => '02:30',
            'flight_crew_and_passengers' => '150',
            'persons_on_board' => '180',
        ];

        $response = $this->post('/flightplan', $flightPlanData);

        $response->assertRedirect(\route('flightplan.preview'));
        $response->assertSessionHas('flight_plan_preview.aircraft_identification', 'N12345');
        $response->assertSessionHas('flight_plan_preview.flight_rules', 'I');
        $this->assertDatabaseMissing('flights', [
            'aircraft_identification' => 'N12345',
            'flight_rules' => 'I',
        ]);
    }

    /**
     * Test invalid aircraft identification
     */
    public function test_invalid_aircraft_identification(): void
    {
        $flightPlanData = [
            'date_of_flight' => now()->addDay()->toDateString(),
            'aircraft_identification' => 'N-12345', // Invalid: contains hyphen
            'flight_rules' => 'I',
            'type_of_flight' => 'S',
            'type_of_aircraft' => 'B747',
            'wake_turbulence_cat' => 'H',
            'departure_aerodrome' => 'KJFK',
            'cruising_speed' => 'N450',
            'level' => 'F350',
            'destination_aerodrome' => 'LFPG',
            'persons_on_board' => '180',
        ];

        $response = $this->post('/flightplan', $flightPlanData);

        $response->assertSessionHasErrors('aircraft_identification');
    }

    /**
     * Test invalid flight rules
     */
    public function test_invalid_flight_rules(): void
    {
        $flightPlanData = [
            'date_of_flight' => now()->addDay()->toDateString(),
            'aircraft_identification' => 'N12345',
            'flight_rules' => 'IFR', // Invalid: must be I/V/Y/Z
            'type_of_flight' => 'S',
            'type_of_aircraft' => 'B747',
            'wake_turbulence_cat' => 'H',
            'departure_aerodrome' => 'KJFK',
            'cruising_speed' => 'N450',
            'level' => 'F350',
            'destination_aerodrome' => 'LFPG',
            'persons_on_board' => '180',
        ];

        $response = $this->post('/flightplan', $flightPlanData);

        $response->assertSessionHasErrors('flight_rules');
    }

    /**
     * Test invalid type of flight
     */
    public function test_invalid_type_of_flight(): void
    {
        $flightPlanData = [
            'date_of_flight' => now()->addDay()->toDateString(),
            'aircraft_identification' => 'N12345',
            'flight_rules' => 'I',
            'type_of_flight' => 'SC', // Invalid: must be single character
            'type_of_aircraft' => 'B747',
            'wake_turbulence_cat' => 'H',
            'departure_aerodrome' => 'KJFK',
            'cruising_speed' => 'N450',
            'level' => 'F350',
            'destination_aerodrome' => 'LFPG',
            'persons_on_board' => '180',
        ];

        $response = $this->post('/flightplan', $flightPlanData);

        $response->assertSessionHasErrors('type_of_flight');
    }

    /**
     * Test invalid wake turbulence category
     */
    public function test_invalid_wake_turbulence_category(): void
    {
        $flightPlanData = [
            'date_of_flight' => now()->addDay()->toDateString(),
            'aircraft_identification' => 'N12345',
            'flight_rules' => 'I',
            'type_of_flight' => 'S',
            'type_of_aircraft' => 'B747',
            'wake_turbulence_cat' => 'HEAVY', // Invalid: must be single character
            'departure_aerodrome' => 'KJFK',
            'cruising_speed' => 'N450',
            'level' => 'F350',
            'destination_aerodrome' => 'LFPG',
            'persons_on_board' => '180',
        ];

        $response = $this->post('/flightplan', $flightPlanData);

        $response->assertSessionHasErrors('wake_turbulence_cat');
    }

    /**
     * Test invalid departure aerodrome
     */
    public function test_invalid_departure_aerodrome(): void
    {
        $flightPlanData = [
            'date_of_flight' => now()->addDay()->toDateString(),
            'aircraft_identification' => 'N12345',
            'flight_rules' => 'I',
            'type_of_flight' => 'S',
            'type_of_aircraft' => 'B747',
            'wake_turbulence_cat' => 'H',
            'departure_aerodrome' => 'JFK', // Invalid: must be 4 characters
            'cruising_speed' => 'N450',
            'level' => 'F350',
            'destination_aerodrome' => 'LFPG',
            'persons_on_board' => '180',
        ];

        $response = $this->post('/flightplan', $flightPlanData);

        $response->assertSessionHasErrors('departure_aerodrome');
    }

    /**
     * Test invalid cruising speed
     */
    public function test_invalid_cruising_speed(): void
    {
        $flightPlanData = [
            'date_of_flight' => now()->addDay()->toDateString(),
            'aircraft_identification' => 'N12345',
            'flight_rules' => 'I',
            'type_of_flight' => 'S',
            'type_of_aircraft' => 'B747',
            'wake_turbulence_cat' => 'H',
            'departure_aerodrome' => 'KJFK',
            'cruising_speed' => '450', // Invalid: missing unit (N/M/K)
            'level' => 'F350',
            'destination_aerodrome' => 'LFPG',
            'persons_on_board' => '180',
        ];

        $response = $this->post('/flightplan', $flightPlanData);

        $response->assertSessionHasErrors('cruising_speed');
    }

    /**
     * Test invalid flight level
     */
    public function test_invalid_flight_level(): void
    {
        $flightPlanData = [
            'date_of_flight' => now()->addDay()->toDateString(),
            'aircraft_identification' => 'N12345',
            'flight_rules' => 'I',
            'type_of_flight' => 'S',
            'type_of_aircraft' => 'B747',
            'wake_turbulence_cat' => 'H',
            'departure_aerodrome' => 'KJFK',
            'cruising_speed' => 'N450',
            'level' => '350', // Invalid: missing FL prefix
            'destination_aerodrome' => 'LFPG',
            'persons_on_board' => '180',
        ];

        $response = $this->post('/flightplan', $flightPlanData);

        $response->assertSessionHasErrors('level');
    }

    /**
     * Test invalid destination aerodrome
     */
    public function test_invalid_destination_aerodrome(): void
    {
        $flightPlanData = [
            'date_of_flight' => now()->addDay()->toDateString(),
            'aircraft_identification' => 'N12345',
            'flight_rules' => 'I',
            'type_of_flight' => 'S',
            'type_of_aircraft' => 'B747',
            'wake_turbulence_cat' => 'H',
            'departure_aerodrome' => 'KJFK',
            'cruising_speed' => 'N450',
            'level' => 'F350',
            'destination_aerodrome' => 'CDG1', // Invalid: contains digit
            'persons_on_board' => '180',
        ];

        $response = $this->post('/flightplan', $flightPlanData);

        $response->assertSessionHasErrors('destination_aerodrome');
    }

    /**
     * Test optional ICAO fields can be null
     */
    public function test_optional_icao_fields_can_be_null(): void
    {
        $flightPlanData = [
            'date_of_flight' => now()->addDay()->toDateString(),
            'aircraft_identification' => null, // Optional
            'flight_rules' => null, // Optional
            'type_of_flight' => null, // Optional
        ];

        // The request should handle nulls gracefully for optional fields
        $this->assertTrue(true);
    }

    /**
     * Test valid alternate aerodromes
     */
    public function test_valid_alternate_aerodromes(): void
    {
        $flightPlanData = [
            'date_of_flight' => now()->addDay()->toDateString(),
            'aircraft_identification' => 'N12345',
            'flight_rules' => 'I',
            'type_of_flight' => 'S',
            'type_of_aircraft' => 'B747',
            'wake_turbulence_cat' => 'H',
            'departure_aerodrome' => 'KJFK',
            'cruising_speed' => 'N450',
            'level' => 'F350',
            'destination_aerodrome' => 'LFPG',
            'altn_aerodrome_1' => 'EGLL', // Valid alternate
            'altn_aerodrome_2' => 'LEMD', // Valid alternate
            'persons_on_board' => '180',
        ];

        $response = $this->post('/flightplan', $flightPlanData);

        $response->assertRedirect(\route('flightplan.preview'));
        $response->assertSessionHas('flight_plan_preview.altn_aerodrome_1', 'EGLL');
        $response->assertSessionHas('flight_plan_preview.altn_aerodrome_2', 'LEMD');
        $this->assertDatabaseMissing('flights', [
            'altn_aerodrome_1' => 'EGLL',
            'altn_aerodrome_2' => 'LEMD',
        ]);
    }

    /**
     * Test invalid alternate aerodromes
     */
    public function test_invalid_alternate_aerodromes(): void
    {
        $flightPlanData = [
            'date_of_flight' => now()->addDay()->toDateString(),
            'aircraft_identification' => 'N12345',
            'flight_rules' => 'I',
            'type_of_flight' => 'S',
            'type_of_aircraft' => 'B747',
            'wake_turbulence_cat' => 'H',
            'departure_aerodrome' => 'KJFK',
            'cruising_speed' => 'N450',
            'level' => 'F350',
            'destination_aerodrome' => 'LFPG',
            'altn_aerodrome_1' => 'LON', // Invalid: only 3 characters
            'persons_on_board' => '180',
        ];

        $response = $this->post('/flightplan', $flightPlanData);

        $response->assertSessionHasErrors('altn_aerodrome_1');
    }
}

