<?php

namespace Tests\Feature;

use App\Domain\FlightPlans\Enums\FlightPlanStatus;
use App\Domain\FlightPlans\Services\FlightPlanPdfService;
use App\Domain\FlightPlans\Services\FlightPlanQrPayloadService;
use App\Domain\FlightPlans\Services\FlightReportQueryService;
use App\Domain\Users\Enums\UserRole;
use App\Filament\Shared\Resources\Flights\FlightResource;
use App\Http\Requests\StoreFlightPlanRequest;
use App\Models\Flight;
use App\Models\FlightPlanEvent;
use App\Models\Operator;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FlightPlanRefactorTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_and_filament_dof_precedence_remains_distinct(): void
    {
        $date = now('UTC')->addDays(2)->toDateString();
        $data = ['date_of_flight' => $date, 'other_information' => 'DOF/20200101 RMK/Keep me', 'persons_on_board' => '003'];
        $request = StoreFlightPlanRequest::create('/flightplan', 'POST', $data);
        $request->setContainer(app());
        $request->validateResolved();

        $this->assertSame(str_replace('-', '', $date), $request->input('other_info_dof'));
        $this->assertSame('003', $request->input('persons_on_board'));
        $filamentData = FlightResource::normalizeFormData($data);
        $this->assertSame('20200101', $filamentData['other_info_dof']);
        $this->assertSame('DOF/20200101 RMK/KEEP ME', $filamentData['other_information']);
        $this->assertSame(3, $filamentData['persons_on_board']);
    }

    public function test_offline_lookup_keeps_only_ten_session_previews_and_the_original_summary_shape(): void
    {
        $flight = Flight::factory()->create();
        $payload = app(FlightPlanQrPayloadService::class)->buildPayload($flight);
        $flight->delete();
        $previews = [];
        for ($index = 0; $index < 10; $index++) {
            $previews['old-'.$index] = ['snapshot' => []];
        }

        $response = $this->withSession(['scanned_flight_plan_previews' => $previews])
            ->post(route('flightplan.scan-qr.lookup'), ['payload' => $payload]);

        $response->assertOk()->assertViewHas('matchedFlight', function (array $matched): bool {
            $this->assertEqualsCanonicalizing([
                'id', 'aircraft_identification', 'date_of_flight', 'proposed_time',
                'departure_aerodrome', 'destination_aerodrome', 'status', 'status_label',
                'status_color', 'view_url', 'can_open',
            ], array_keys($matched));
            $this->assertSame('verified_qr_only', $matched['status']);
            $this->assertTrue($matched['can_open']);

            return true;
        });
        $stored = session('scanned_flight_plan_previews');
        $this->assertCount(10, $stored);
        $this->assertArrayNotHasKey('old-0', $stored);
        $response = $this->get(route('flightplan.scan-qr.preview', ['token' => array_key_last($stored)]));
        $response->assertOk()->assertViewHas('showReviewActions', false);
    }

    public function test_public_pdf_download_requires_a_session_grant_and_keeps_exact_file_selection(): void
    {
        Storage::fake('public');
        $flight = Flight::factory()->create();
        Storage::disk('public')->put('flight-plans/20260101/exact.pdf', '%PDF-test');
        $url = route('flights.pdf.download', ['flight' => $flight, 'file' => 'exact.pdf']);
        $this->get($url)->assertForbidden();
        $this->withSession(['public_flight_access' => [(string) $flight->id]])->get($url)
            ->assertOk()->assertDownload('exact.pdf');
        $this->assertSame('flight-plans/20260101/exact.pdf', app(FlightPlanPdfService::class)->resolveRequestedPath('../exact.pdf'));
    }

    public function test_rejection_validation_does_not_persist_and_success_records_the_event(): void
    {
        $atmo = User::factory()->create(['role' => UserRole::Atmo, 'is_active' => true, 'wiresign' => 'AT', 'station' => 'RPUS']);
        $flight = Flight::factory()->create();
        $this->actingAs($atmo)->post(route('flights.reject', $flight), ['rejection_reason' => ''])
            ->assertSessionHasErrors('rejection_reason');
        $this->assertSame(FlightPlanStatus::Pending, $flight->refresh()->status);
        $this->assertSame(0, $flight->events()->where('event_type', FlightPlanEvent::TYPE_ATC_REJECTED)->count());
        $this->post(route('flights.reject', $flight), ['rejection_reason' => '  Route incomplete  '])
            ->assertRedirect(route('flights.view', $flight));
        $this->assertSame('Route incomplete', $flight->refresh()->rejection_reason);
        $this->assertSame(1, $flight->events()->where('event_type', FlightPlanEvent::TYPE_ATC_REJECTED)->count());
    }

    public function test_qr_validation_preserves_messages_and_pilot_authorization_precedes_validation(): void
    {
        $this->post(route('flightplan.scan-qr.lookup'), [])
            ->assertSessionHasErrors(['payload' => 'Paste or scan a QR payload first.']);
        $this->post(route('flightplan.edit-from-qr'), [])
            ->assertSessionHasErrors(['payload' => 'A QR payload is required.']);
        $pilot = User::factory()->create(['role' => UserRole::Pilot]);
        $this->actingAs($pilot)->post(route('flightplan.scan-qr.lookup'), [])->assertForbidden();
        $this->post(route('flightplan.edit-from-qr'), [])->assertForbidden();
    }

    public function test_report_exports_preserve_date_filter_view_data_and_pdf_response(): void
    {
        $atmo = User::factory()->create(['role' => UserRole::Atmo, 'is_active' => true, 'station' => 'RPUS']);
        $date = now('UTC')->addDay()->toDateString();
        $flight = Flight::factory()->create([
            'status' => FlightPlanStatus::Accepted, 'received_facility' => 'RPUS',
            'date_of_flight' => $date, 'time_airborne' => '12:30',
        ]);
        Flight::factory()->create([
            'status' => FlightPlanStatus::Accepted, 'received_facility' => 'RPUS',
            'date_of_flight' => now('UTC')->addDays(2)->toDateString(), 'time_airborne' => '12:30',
        ]);
        foreach ([
            'reports.abbreviated.pdf' => 'reports.abbreviated-flight-report-pdf',
            'reports.post-ops-log.pdf' => 'reports.post-ops-log-pdf',
        ] as $route => $view) {
            Pdf::shouldReceive('loadView')->once()
                ->withArgs(function (string $actualView, array $data) use ($view, $flight, $date, $atmo): bool {
                    $this->assertSame($view, $actualView);
                    $this->assertSame([$flight->id], $data['flights']->modelKeys());
                    $this->assertSame($date, $data['selectedDate']);
                    $this->assertSame($atmo->wiresign, $data['generatedBy']);
                    $this->assertSame('1230', $data['formatTime']('12:30'));

                    return true;
                })->andReturnSelf();
            Pdf::shouldReceive('setPaper')->once()->with('a4', 'landscape')->andReturnSelf();
            Pdf::shouldReceive('stream')->once()->andReturn(response('%PDF-report', 200, ['Content-Type' => 'application/pdf']));
            $this->actingAs($atmo)->get(route($route, ['date' => $date]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        }
    }

    public function test_report_queries_keep_facility_status_airborne_and_operator_filters(): void
    {
        $operator = Operator::factory()->create();
        $otherOperator = Operator::factory()->create();
        $dispatch = User::factory()->create(['role' => UserRole::Dispatch, 'is_active' => true, 'operator_id' => $operator->id]);
        $base = ['operator_id' => $operator->id, 'status' => FlightPlanStatus::Accepted, 'received_facility' => 'RPUS'];
        $ground = Flight::factory()->create($base);
        $airborne = Flight::factory()->create([...$base, 'time_airborne' => '12:30']);
        Flight::factory()->create([...$base, 'operator_id' => $otherOperator->id]);
        Flight::factory()->create([...$base, 'received_facility' => 'RPLL']);
        Flight::factory()->create([...$base, 'status' => FlightPlanStatus::Rejected]);
        $queries = app(FlightReportQueryService::class);
        $this->assertEqualsCanonicalizing([$ground->id, $airborne->id], $queries->abbreviated($dispatch)->pluck('id')->all());
        $this->assertSame([$airborne->id], $queries->postOperations($dispatch)->pluck('id')->all());
        $this->assertSame([], $queries->abbreviated(null)->pluck('id')->all());
        $this->actingAs($dispatch)->get(route('reports.abbreviated.pdf'))->assertForbidden();
    }
}
