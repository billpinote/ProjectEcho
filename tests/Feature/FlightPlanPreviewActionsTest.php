<?php

namespace Tests\Feature;

use App\Domain\FlightPlans\Services\FlightPlanQrPayloadService;
use App\Domain\Users\Enums\UserRole;
use App\Models\Flight;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
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

        $pilot = User::factory()->create([
            'role' => UserRole::Pilot,
            'is_active' => true,
        ]);

        $flightData = $this->previewFlightPlanData([
            'departure_aerodrome' => 'RPUS',
        ]);

        $response = $this
            ->actingAs($pilot)
            ->withSession(['flight_plan_preview' => $flightData])
            ->post(route('flightplan.approve'));

        $this->assertDatabaseHas('flights', [
            'aircraft_identification' => 'N12345',
            'flight_rules' => 'I',
            'filed_by_user_id' => $pilot->id,
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

    public function test_authenticated_pilot_filing_records_filed_by_user_id(): void
    {
        Storage::fake('public');

        $pilot = User::factory()->create([
            'role' => UserRole::Pilot,
            'is_active' => true,
        ]);

        $flightData = $this->previewFlightPlanData();

        $this->actingAs($pilot)
            ->withSession(['flight_plan_preview' => $flightData])
            ->post(route('flightplan.approve'))
            ->assertForbidden();

        $flightData['departure_aerodrome'] = 'RPUS';

        $this->actingAs($pilot)
            ->withSession(['flight_plan_preview' => $flightData])
            ->post(route('flightplan.approve'))
            ->assertRedirect();

        $this->assertDatabaseHas('flights', [
            'aircraft_identification' => 'N12345',
            'filed_by_user_id' => $pilot->id,
        ]);
    }

    public function test_non_rpus_departure_previews_as_pdf_only_without_qr_actions(): void
    {
        $response = $this
            ->withSession(['flight_plan_preview' => $this->previewFlightPlanData([
                'departure_aerodrome' => 'RPLL',
            ])])
            ->get(route('flightplan.preview'));

        $response
            ->assertOk()
            ->assertSee('PDF ONLY - NOT FILED WITH RPUS')
            ->assertSee('Creates a printable flight plan only. This will not be filed with RPUS and no QR code will be generated.')
            ->assertSee(route('flightplan.pdf-only'), false)
            ->assertDontSee('QR will be generated')
            ->assertDontSee('data:image/svg+xml;base64,', false);

        $this->assertDatabaseCount('flights', 0);
    }

    public function test_pdf_only_generation_streams_pdf_without_creating_flight_or_qr_payload(): void
    {
        Storage::fake('public');

        $this->mock(FlightPlanQrPayloadService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('buildPayload')->never();
        });

        $response = $this
            ->withSession(['flight_plan_preview' => $this->previewFlightPlanData([
                'departure_aerodrome' => 'RPLL',
            ])])
            ->post(route('flightplan.pdf-only'));

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertDatabaseCount('flights', 0);
        $this->assertSame(0, Flight::query()->pendingActive()->count());
        $this->assertEmpty(Storage::disk('public')->allFiles('flight-plans'));
        $this->assertStringNotContainsString('PDF ONLY', $response->getContent());
        $this->assertStringNotContainsString('NOT FILED WITH RPUS', $response->getContent());
    }

    public function test_pdf_only_generation_rejects_rpus_preview(): void
    {
        $this
            ->withSession(['flight_plan_preview' => $this->previewFlightPlanData([
                'departure_aerodrome' => 'RPUS',
            ])])
            ->post(route('flightplan.pdf-only'))
            ->assertForbidden();

        $this->assertDatabaseCount('flights', 0);
    }

    public function test_unauthorized_users_cannot_post_directly_to_operational_filing(): void
    {
        $this
            ->withSession(['flight_plan_preview' => $this->previewFlightPlanData([
                'departure_aerodrome' => 'RPUS',
            ])])
            ->post(route('flightplan.approve'))
            ->assertForbidden();

        $avsec = User::factory()->create([
            'role' => UserRole::Avsec,
            'is_active' => true,
        ]);

        $this->actingAs($avsec)
            ->withSession(['flight_plan_preview' => $this->previewFlightPlanData([
                'departure_aerodrome' => 'RPUS',
            ])])
            ->post(route('flightplan.approve'))
            ->assertForbidden();

        $this->assertDatabaseCount('flights', 0);
    }

    public function test_qr_page_shows_compact_mobile_pass_and_save_actions(): void
    {
        Storage::fake('public');

        $flight = Flight::create($this->previewFlightPlanData());
        $storedPdfPath = 'flight-plans/'.now('UTC')->format('Ymd').'/N12345'.str_replace('-', '', (string) $flight->date_of_flight).'143000.pdf';

        Storage::disk('public')->put($storedPdfPath, 'pdf');

        $response = $this
            ->withSession(['public_flight_access' => [$flight->getKey()]])
            ->get(route('flights.qr', [
                'flight' => $flight,
                'file' => basename($storedPdfPath),
            ]));

        $response
            ->assertOk()
            ->assertSee('ECHO · FLIGHT PLAN')
            ->assertSee('FLIGHT PLAN READY')
            ->assertSee('N12345')
            ->assertSee('KJFK')
            ->assertSee('LFPG')
            ->assertSee(strtoupper(now('UTC')->addDay()->format('d M Y')))
            ->assertSee('REV 1')
            ->assertDontSee('READY FOR ATC')
            ->assertSee('Save QR to Device')
            ->assertSee('Share')
            ->assertSee('Back to Dashboard')
            ->assertDontSee('Show This QR To ATC')
            ->assertSeeInOrder([
                '</section>',
                '<div class="qr-actions"',
                'Save QR to Device',
                'Share',
                'Download PDF',
                'Back to Dashboard',
            ], false)
            ->assertSee(route('flights.qr.download', ['flight' => $flight]), false)
            ->assertSee(route('flights.pdf.download', [
                'flight' => $flight,
                'file' => basename($storedPdfPath),
            ]), false)
            ->assertSee('data:image/svg+xml;base64,', false);
    }

    public function test_qr_page_back_to_dashboard_uses_the_authenticated_role_panel(): void
    {
        $pilot = User::factory()->create([
            'role' => UserRole::Pilot,
            'is_active' => true,
        ]);
        $flight = Flight::create($this->previewFlightPlanData([
            'filed_by_user_id' => $pilot->getKey(),
            'pilot_id' => $pilot->getKey(),
            'pilot_in_command_user_id' => $pilot->getKey(),
        ]));

        $response = $this
            ->actingAs($pilot)
            ->get(route('flights.qr', ['flight' => $flight]));

        $response
            ->assertOk()
            ->assertSee('href="'.url('/pilot').'"', false)
            ->assertSee('Back to Dashboard');
    }

    public function test_qr_image_download_returns_server_generated_png(): void
    {
        $flight = Flight::create($this->previewFlightPlanData([
            'aircraft_identification' => 'SUMAIR1',
            'departure_aerodrome' => 'RPLL',
            'proposed_time' => '01:05',
        ]));

        $response = $this
            ->withSession(['public_flight_access' => [$flight->getKey()]])
            ->get(route('flights.qr.download', ['flight' => $flight]));

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('Content-Disposition', 'attachment; filename="ECHO-SUMAIR1-'.str_replace('/', '-', (string) $flight->date_of_flight).'.png"');

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
