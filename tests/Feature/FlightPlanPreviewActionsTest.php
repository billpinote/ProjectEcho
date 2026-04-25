<?php

namespace Tests\Feature;

use App\Models\Flight;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FlightPlanPreviewActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_keeps_validated_flight_plan_in_preview_session(): void
    {
        $response = $this->post(route('flightplan.store'), $this->validFlightPlanData());

        $response
            ->assertRedirect(route('flightplan.preview'))
            ->assertSessionHas('flight_plan_preview.aircraft_identification', 'N12345')
            ->assertSessionHas('flight_plan_preview.proposed_time', '14:30');

        $this->assertDatabaseCount('flights', 0);
    }

    public function test_store_rejects_flight_plan_when_proposed_utc_time_has_passed(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-04-25 12:00:00', 'UTC'));

        $response = $this->post(route('flightplan.store'), $this->validFlightPlanData([
            'date_of_flight' => '2026-04-25',
            'proposed_time' => '1130',
            'other_information' => 'DOF/20260425',
        ]));

        $response
            ->assertRedirect()
            ->assertSessionHasErrors([
                'date_of_flight' => 'The date of flight and proposed time must be in the future.',
            ]);

        $this->assertDatabaseCount('flights', 0);
    }

    public function test_store_allows_flight_plan_when_proposed_utc_time_is_still_future(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-04-25 12:00:00', 'UTC'));

        $response = $this->post(route('flightplan.store'), $this->validFlightPlanData([
            'date_of_flight' => '2026-04-25',
            'proposed_time' => '1230',
            'other_information' => 'DOF/20260425',
        ]));

        $response
            ->assertRedirect(route('flightplan.preview'))
            ->assertSessionHas('flight_plan_preview.proposed_time', '12:30');

        $this->assertDatabaseCount('flights', 0);
    }

    public function test_approve_creates_flight_generates_pdf_and_clears_preview_session(): void
    {
        Storage::fake('public');

        $flightData = $this->previewFlightPlanData();

        $response = $this
            ->withSession(['flight_plan_preview' => $flightData])
            ->post(route('flightplan.approve'));

        $this->assertDatabaseHas('flights', [
            'aircraft_identification' => 'N12345',
            'flight_rules' => 'I',
        ]);

        $flight = Flight::firstOrFail();
        $expectedFile = 'flight-plans/'.now('UTC')->format('Ymd').'/N12345'.str_replace('-', '', $flightData['date_of_flight']).'143000.pdf';

        $response
            ->assertRedirect(route('flights.qr', [
                'flight' => $flight,
                'file' => basename($expectedFile),
            ]))
            ->assertSessionMissing('flight_plan_preview')
            ->assertSessionMissing('status')
            ->assertSessionMissing('pdf_download_url');

        Storage::disk('public')->assertExists($expectedFile);
    }

    public function test_qr_page_shows_large_qr_and_pdf_download_button(): void
    {
        Storage::fake('public');

        $flight = Flight::create($this->previewFlightPlanData());
        $storedPdfPath = 'flight-plans/'.now('UTC')->format('Ymd').'/N12345'.str_replace('-', '', (string) $flight->date_of_flight).'143000.pdf';

        Storage::disk('public')->put($storedPdfPath, 'pdf');

        $response = $this->get(route('flights.qr', [
            'flight' => $flight,
            'file' => basename($storedPdfPath),
        ]));

        $response
            ->assertOk()
            ->assertSee('Flight Plan Ready')
            ->assertSee('Show This QR To ATC')
            ->assertSee('Download QR')
            ->assertSee('Download PDF')
            ->assertSee(route('flights.qr.download', ['flight' => $flight]), false)
            ->assertSee(route('flights.pdf.download', [
                'flight' => $flight,
                'file' => basename($storedPdfPath),
            ]), false)
            ->assertSee('data:image/svg+xml;base64,', false);
    }

    public function test_qr_image_download_returns_server_generated_png(): void
    {
        $flight = Flight::create($this->previewFlightPlanData([
            'aircraft_identification' => 'SUMAIR1',
            'departure_aerodrome' => 'RPLL',
            'proposed_time' => '01:05',
        ]));

        $response = $this->get(route('flights.qr.download', ['flight' => $flight]));

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('Content-Disposition', 'attachment; filename="flight-plan-qr-SUMAIR1.png"');

        $this->assertStringStartsWith("\x89PNG", $response->getContent());
    }

    public function test_edit_preview_returns_to_form_with_old_input_without_creating_flight(): void
    {
        $flightData = $this->previewFlightPlanData([
            'dinghies_enabled' => true,
            'dinghies_number' => 2,
            'authorized_representative_enabled' => true,
            'authorized_representative_name' => 'DISPATCHER ONE',
        ]);

        $response = $this
            ->withSession(['flight_plan_preview' => $flightData])
            ->post(route('flightplan.edit-preview'));

        $response
            ->assertRedirect(route('flightplan'))
            ->assertSessionHas('flight_plan_preview.aircraft_identification', 'N12345')
            ->assertSessionHas('_old_input.aircraft_identification', 'N12345')
            ->assertSessionHas('_old_input.proposed_time', '1430')
            ->assertSessionHas('_old_input.dinghies_enabled', true)
            ->assertSessionHas('_old_input.authorized_representative_enabled', true);

        $this->assertDatabaseCount('flights', 0);
    }

    public function test_discard_preview_clears_session_and_flashes_warning(): void
    {
        $response = $this
            ->withSession(['flight_plan_preview' => $this->previewFlightPlanData()])
            ->post(route('flightplan.discard-preview'));

        $response
            ->assertRedirect(route('flightplan'))
            ->assertSessionMissing('flight_plan_preview')
            ->assertSessionHas('discard_warning', 'Flight plan discarded.');

        $this->assertDatabaseCount('flights', 0);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validFlightPlanData(array $overrides = []): array
    {
        return array_merge([
            'date_of_flight' => now('UTC')->addDay()->toDateString(),
            'aircraft_identification' => 'N12345',
            'flight_rules' => 'I',
            'type_of_flight' => 'S',
            'number' => '1',
            'type_of_aircraft' => 'B747',
            'wake_turbulence_cat' => 'H',
            'equipment_10a' => 'S',
            'equipment_10b' => 'C',
            'departure_aerodrome' => 'KJFK',
            'proposed_time' => '1430',
            'cruising_speed' => 'N450',
            'level' => 'F350',
            'route' => 'DCT',
            'destination_aerodrome' => 'LFPG',
            'total_eet' => '0230',
            'endurance' => '0400',
            'persons_on_board' => '180',
            'other_information' => 'DOF/'.now('UTC')->addDay()->format('Ymd'),
            'pilot_in_command' => 'CAPTAIN TEST',
            'pilot_license_no' => 'LIC123',
            'pilot_ratings' => 'IR',
            'license_expiry_date' => now('UTC')->addYear()->toDateString(),
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function previewFlightPlanData(array $overrides = []): array
    {
        $data = $this->validFlightPlanData();

        $data['date_of_filing'] = now('UTC')->toDateString();
        $data['proposed_time'] = '14:30';
        $data['total_eet'] = '02:30';
        $data['endurance'] = '04:00';
        $data['persons_on_board'] = 180;
        $data['dinghies_enabled'] = false;
        $data['authorized_representative_enabled'] = false;

        return array_merge($data, $overrides);
    }
}
