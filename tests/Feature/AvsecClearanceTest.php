<?php

namespace Tests\Feature;

use App\Domain\FlightPlans\Enums\FlightPlanStatus;
use App\Domain\FlightPlans\Services\AvsecVerificationService;
use App\Domain\FlightPlans\Services\FlightManifestService;
use App\Domain\FlightPlans\Services\FlightPlanPdfService;
use App\Domain\FlightPlans\Support\FlightStatusDisplay;
use App\Domain\Users\Enums\UserRole;
use App\Filament\Panels\Avsec\Resources\AllFlightPlans\Pages\ListAllFlights;
use App\Filament\Panels\Avsec\Resources\AvsecVerifications\AvsecVerificationResource;
use App\Filament\Panels\Avsec\Resources\AvsecVerifications\Pages\ListAvsecVerifications;
use App\Filament\Panels\Avsec\Resources\AvsecVerifications\Pages\ViewAvsecVerification;
use App\Filament\Panels\Avsec\Resources\FiledRpusDepartures\Pages\ListFiledRpusDepartures;
use App\Filament\Panels\Avsec\Widgets\AllFlights;
use App\Models\AvsecVerification;
use App\Models\Flight;
use App\Models\FlightPlanEvent;
use App\Models\Operator;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentColor;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\SignsFlightDocuments;
use Tests\TestCase;

class AvsecClearanceTest extends TestCase
{
    use RefreshDatabase, SignsFlightDocuments;

    private function readyFlight(array $attributes = [], array $manifestAttributes = []): Flight
    {
        $flight = Flight::factory()->create(['date_of_flight' => now('Asia/Manila')->toDateString(), 'proposed_time' => now('UTC')->format('Hi'), ...$attributes])->refresh();
        app(FlightManifestService::class)->save($flight, $flight->filedBy, [
            'aircraft_owner_name' => 'Owner', 'aircraft_owner_contact_number' => '1234567',
            'home_base_hangar' => 'RPUS', 'coa_expiry_date' => now()->addYear()->toDateString(),
            'total_cargo_weight' => 0,
            'persons' => [['full_name' => $flight->pilot_in_command, 'nationality' => 'PH', 'classification' => 'pic']],
            ...$manifestAttributes,
        ]);
        $this->signFlightDocuments($flight);

        return $flight->refresh();
    }

    private function officer(array $attributes = []): User
    {
        $officer = User::factory()->create(['role' => UserRole::Avsec, 'station' => 'RPUS', ...$attributes]);
        $this->actingAs($officer);
        Filament::setCurrentPanel(Filament::getPanel('avsec'));

        return $officer;
    }

    private function decision(Flight $flight, string $decision = 'grant'): array
    {
        return ['decision' => $decision, 'confirmation' => '1', 'reason' => 'Offline clearance denied',
            'reference' => 'PNP-456', 'reviewed_fingerprint' => app(AvsecVerificationService::class)->reviewFingerprint($flight->fresh())];
    }

    private function assertTopPreviewPill(TestResponse $response, string $label, string $color): void
    {
        $document = new \DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        $pill = (new \DOMXPath($document))->query('//span[@aria-label="Flight plan status"]')->item(0);
        $this->assertNotNull($pill, 'The actual status pill above the flight plan must be present.');
        $this->assertSame($label, trim($pill->textContent));
        $this->assertSame($color, $pill->getAttribute('data-avsec-preview-color'));
        $this->assertStringNotContainsString('echo-preview-status-pending', $pill->getAttribute('class'));
        $palette = FilamentColor::getColor($color);
        $this->assertStringContainsString('color: '.$palette[700], $pill->getAttribute('style'));
        $this->assertStringContainsString('background: '.$palette[50], $pill->getAttribute('style'));
        $this->assertStringContainsString('border-color: '.$palette[300], $pill->getAttribute('style'));
    }

    public function test_native_row_links_open_previews_in_a_new_tab_without_redundant_actions(): void
    {
        $flight = $this->readyFlight();
        $this->officer();
        foreach ([AllFlights::class, ListFiledRpusDepartures::class, ListAllFlights::class] as $component) {
            $page = Livewire::test($component)->assertCanSeeTableRecords([$flight]);
            $table = $page->instance()->getTable();
            $this->assertSame([], $table->getRecordActions());
            $this->assertNull($table->getRecordAction($flight));
            $this->assertSame(route('flights.view', $flight), $table->getRecordUrl($flight));
            $this->assertTrue($table->shouldOpenRecordUrlInNewTab($flight));
            $page->assertSee('target="_blank"', false)->assertSee('echo-avsec-record-link', false)
                ->assertSee('href="'.route('flights.view', $flight).'"', false);
        }
    }

    public function test_document_cards_show_registered_operator_and_actual_manifest_totals(): void
    {
        $operator = Operator::factory()->create(['name' => 'Alpha Aviation Group', 'icao_code' => 'AAG']);
        $flight = $this->readyFlight(['operator_id' => $operator->id, 'type_of_aircraft' => 'B350', 'pilot_in_command' => 'Captain Example', 'persons_on_board' => 3], [
            'cargo_type' => 'Equipment', 'cargo_quantity' => '2 crates + 3 boxes', 'total_cargo_weight' => 125.5,
            'persons' => [
                ['full_name' => 'Captain Example', 'nationality' => 'PH', 'classification' => 'pic'],
                ['full_name' => 'Crew Member', 'nationality' => 'PH', 'classification' => 'crew'],
                ['full_name' => 'Passenger', 'nationality' => 'PH', 'classification' => 'passenger'],
            ],
        ]);
        $this->confirmFlightAvsec($flight);
        $this->officer();
        $verification = $flight->avsecVerification()->sole();
        $page = Livewire::test(ViewAvsecVerification::class, ['record' => $verification->getRouteKey()])
            ->assertSuccessful()->assertSchemaComponentStateSet('owner_operator', 'Alpha Aviation Group')
            ->assertSchemaComponentStateSet('flight.type_of_aircraft', 'B350')
            ->assertSchemaComponentStateSet('flight.pilot_in_command', 'Captain Example')
            ->assertSchemaComponentStateSet('total_pob', 3)
            ->assertSchemaComponentStateSet('cargo_quantity', '2 crates + 3 boxes')
            ->assertSchemaComponentStateSet('cargo_weight', '125.5 kg')
            ->assertDontSee('Includes Pilot, crew, and passengers.')
            ->assertDontSee('Declared items or packages, including any recorded units.')
            ->assertDontSee('(unit not recorded)')
            ->assertSeeInOrder(['Total POB', 'Cargo Quantity', 'Cargo Weight'])
            ->assertSeeInOrder(['Owner / Operator', 'Aircraft Type', 'Pilot'])
            ->assertSee('Download PDF')->assertSee(route('flights.pdf.download', $flight), false)
            ->assertSee(e(route('flights.manifest.pdf', ['flight' => $flight, 'download' => 1])), false);
        $manifest = $flight->manifest()->firstOrFail();
        $path = app(FlightPlanPdfService::class)->storedPaths($flight)->firstOrFail();
        $pdf = $this->get(route('flights.pdf.download', $flight))->assertOk()->assertDownload(basename($path));
        $this->assertSame(Storage::disk(app(FlightPlanPdfService::class)->diskFor($path))->get($path), $pdf->streamedContent());
        $manifestPdf = $this->get(route('flights.manifest.pdf', ['flight' => $flight, 'download' => 1]))
            ->assertOk()->assertDownload('manifest-flight-'.$flight->id.'.pdf')->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame($manifest->accepted_pdf_sha256, hash('sha256', $manifestPdf->getContent()));
        $atmo = User::factory()->create(['role' => UserRole::Atmo, 'station' => 'RPUS']);
        $preview = $this->actingAs($atmo)->get(route('flights.view', $flight))->assertOk();
        $this->assertTopPreviewPill($preview, 'For ATMO Signature', 'gray');
    }

    public function test_cards_use_owner_fallback_and_do_not_invent_cargo_units(): void
    {
        $flight = $this->readyFlight([], ['cargo_type' => 'Boxes', 'cargo_quantity' => '12', 'total_cargo_weight' => 85]);
        $this->confirmFlightAvsec($flight);
        $this->officer();
        Livewire::test(ViewAvsecVerification::class, ['record' => $flight->avsecVerification()->sole()->getRouteKey()])
            ->assertSchemaComponentStateSet('owner_operator', 'Owner')
            ->assertSchemaComponentStateSet('total_pob', 1)
            ->assertSchemaComponentStateSet('cargo_quantity', '12')
            ->assertDontSee('(unit not recorded)')
            ->assertSchemaComponentStateSet('cargo_weight', '85 kg');
    }

    public function test_cleared_rows_link_to_verification_id_and_authorized_documents_remain_restricted(): void
    {
        Flight::factory()->create();
        $flight = $this->readyFlight();
        $this->confirmFlightAvsec($flight);
        $verification = $flight->avsecVerification()->sole();
        $this->assertNotSame($flight->id, $verification->id);
        $this->officer();
        Livewire::test(ViewAvsecVerification::class, ['record' => $verification->getRouteKey()])
            ->assertSchemaComponentStateSet('cargo_quantity', null)
            ->assertSchemaComponentStateSet('cargo_weight', '0 kg');
        $page = Livewire::test(ListAvsecVerifications::class)->assertCanSeeTableRecords([$verification]);
        $table = $page->instance()->getTable();
        $url = AvsecVerificationResource::getUrl('view', ['record' => $verification], panel: 'avsec');
        $this->assertSame($url, $table->getRecordUrl($verification));
        $this->assertTrue($table->shouldOpenRecordUrlInNewTab($verification));
        $this->assertSame([], $table->getRecordActions());
        $page->assertSee('href="'.$url.'"', false)->assertSee('target="_blank"', false);
        foreach ([['station' => 'RPLL'], ['is_active' => false], ['role' => UserRole::Pilot]] as $attributes) {
            $this->officer($attributes);
            $this->assertContains($this->get($url)->getStatusCode(), [403, 404]);
            $this->get(route('flights.pdf.download', $flight))->assertForbidden();
            $this->get(route('flights.manifest.pdf', ['flight' => $flight, 'download' => 1]))->assertForbidden();
        }
    }

    #[DataProvider('legacyDocuments')]
    public function test_legacy_missing_or_incomplete_documents_do_not_offer_misleading_downloads(string $kind): void
    {
        $flight = $kind === 'missing_pdf' ? $this->readyFlight() : Flight::factory()->create()->refresh();
        if ($kind === 'incomplete') {
            app(FlightManifestService::class)->save($flight, $flight->filedBy, ['total_cargo_weight' => 0]);
        }
        $officer = $this->officer();
        // Model a historical clearance whose required supporting documents are unavailable.
        $verification = new AvsecVerification;
        $verification->forceFill([
            'flight_id' => $flight->id, 'verifier_user_id' => $officer->id, 'verifier_name' => $officer->fullName(),
            'station' => 'RPUS', 'verified_at' => now('UTC'), 'flight_revision' => $flight->revision_number,
            'content_sha256' => str_repeat('0', 64), 'approved_content' => [],
        ])->save();
        Livewire::test(ViewAvsecVerification::class, ['record' => $verification->getRouteKey()])
            ->assertSuccessful()->assertSchemaComponentStateSet('owner_operator', $kind === 'missing_pdf' ? 'Owner' : null)
            ->assertSchemaComponentStateSet('manifest_status', 'Manifest Required')
            ->assertSchemaComponentStateSet('cargo_quantity', null)
            ->assertSchemaComponentStateSet('cargo_weight', $kind === 'missing' ? null : '0 kg')
            ->assertSchemaComponentStateSet('flight_plan_status', 'PDF Unavailable')
            ->assertDontSee('Download PDF')
            ->assertDontSee(route('flights.pdf.download', $flight), false)
            ->assertDontSee(e(route('flights.manifest.pdf', ['flight' => $flight, 'download' => 1])), false);
    }

    public static function legacyDocuments(): array
    {
        return [['missing'], ['incomplete'], ['missing_pdf']];
    }

    public function test_avsec_pilot_and_atmo_can_inspect_unsigned_plan_without_operational_authority(): void
    {
        $flight = Flight::factory()->create(['status' => FlightPlanStatus::AwaitingPic, 'pic_authorization_status' => 'pending']);
        $flight->forceFill(['prepared_by_user_id' => $flight->filed_by_user_id])->saveQuietly();
        $officer = $this->officer();
        $preview = $this->get(route('flights.view', $flight))->assertOk()->assertViewIs('flightplan.pdf')
            ->assertViewHas('showAvsecActions', true)->assertViewHas('canDecideClearance', false)
            ->assertSee('Manifest Required')->assertSee('Close Preview')->assertSee('Grant Clearance')->assertSee('Deny Clearance')
            ->assertSee('data-avsec-status-color="warning"', false)
            ->assertSee('href="'.url('/avsec').'"', false)->assertDontSee('/admin')->assertDontSee('import-scan-qr');
        $this->assertTopPreviewPill($preview, 'Manifest Required', 'warning');
        $this->assertSame(FlightPlanStatus::AwaitingPic, $flight->fresh()->status);
        $this->assertFalse($officer->can('accept', $flight));
        $atmo = User::factory()->create(['role' => UserRole::Atmo, 'station' => 'RPUS']);
        $this->actingAs($atmo)->get(route('flights.view', $flight))->assertOk()->assertViewHas('canAcceptFlightPlan', false);
        $this->actingAs($flight->filedBy)->get(route('flights.view', $flight))->assertOk()->assertViewIs('flightplan.pdf')->assertSeeText('Manifest Required');
    }

    public function test_signed_documents_are_available_before_atmo_acceptance(): void
    {
        $flight = $this->readyFlight();
        $this->officer();
        $this->assertSame('For AVSEC Clearance', FlightStatusDisplay::badge($flight, UserRole::Avsec)['label']);
        $preview = $this->get(route('flights.view', $flight))->assertOk()->assertViewHas('canDecideClearance', true)
            ->assertSee('For AVSEC Clearance')->assertSee('Review Manifest')->assertSee(route('flights.manifest.pdf', $flight), false)
            ->assertSee('data-avsec-status-color="warning"', false)
            ->assertSee(AvsecVerificationService::CONFIRMATION)->assertDontSee('Accept Flight Plan')->assertDontSee('Reject Flight Plan')
            ->assertSee('echo-avsec-toolbar', false);
        $this->assertTopPreviewPill($preview, 'For AVSEC Clearance', 'warning');
        $this->get(route('flights.manifest.pdf', $flight))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame(FlightPlanStatus::Pending, $flight->fresh()->status);
    }

    #[DataProvider('decisions')]
    public function test_clearance_records_officer_and_audit_without_changing_flight_or_pilot_decisions(string $decision): void
    {
        $flight = $this->readyFlight();
        $officer = $this->officer();
        $before = $flight->getAttributes();
        $manifestBefore = $flight->manifest->only(['aircraft_owner_name', 'total_cargo_weight', 'cargo_type']);
        $this->post(route('flights.avsec-clearance', $flight), $this->decision($flight, $decision))->assertSessionHasNoErrors()->assertRedirect(route('flights.view', $flight));
        $flight->refresh();
        $this->assertSame($before, $flight->getAttributes());
        $this->assertSame($manifestBefore, $flight->manifest->only(array_keys($manifestBefore)));
        $event = $flight->events()->where('event_type', $decision === 'grant' ? FlightPlanEvent::TYPE_AVSEC_VERIFIED : FlightPlanEvent::TYPE_AVSEC_DENIED)->sole();
        $this->assertSame($officer->id, $event->actor_user_id);
        $this->assertNotNull($event->created_at);
        $this->assertSame('PNP-456', $event->new_values['pnp_reference_number']);
        $this->assertSame($decision === 'grant', $flight->avsecVerification()->exists());
        $expected = $decision === 'grant' ? 'Clearance Granted' : 'Clearance Denied';
        $color = $decision === 'grant' ? 'success' : 'danger';
        $this->assertSame($color, FlightStatusDisplay::avsecBadge($flight)['color']);
        $preview = $this->get(route('flights.view', $flight))->assertOk()->assertSee($expected)->assertViewHas('canDecideClearance', false)
            ->assertSee('data-avsec-status-color="'.$color.'"', false)
            ->assertSee('color: '.FilamentColor::getColor($color)[600], false);
        $this->assertTopPreviewPill($preview, $decision === 'grant' ? 'For ATMO Signature' : $expected, $decision === 'grant' ? 'gray' : $color);
        if ($decision === 'deny') {
            $this->assertSame('Offline clearance denied', $event->reason);
            $this->assertFalse(app(AvsecVerificationService::class)->validFor($flight));
            $atmo = User::factory()->create(['role' => UserRole::Atmo]);
            $this->assertFalse($atmo->can('accept', $flight));
        }
        foreach (['grant', 'deny'] as $repeat) {
            $this->post(route('flights.avsec-clearance', $flight), $this->decision($flight, $repeat))->assertSessionHasErrors('avsec');
        }
        $this->assertSame(1, $flight->events()->whereIn('event_type', [FlightPlanEvent::TYPE_AVSEC_VERIFIED, FlightPlanEvent::TYPE_AVSEC_DENIED])->count());
    }

    public static function decisions(): array
    {
        return [['grant'], ['deny']];
    }

    #[DataProvider('missingDocuments')]
    public function test_missing_or_incomplete_manifest_stays_visible_but_blocks_both_decisions(string $kind): void
    {
        $flight = $this->readyFlight();
        if ($kind === 'missing') {
            $flight->manifest->delete();
        } else {
            $flight->manifest->update(['aircraft_owner_contact_number' => null]);
        }
        $flight->refresh();
        $this->officer();
        $this->get(route('flights.view', $flight))->assertOk()->assertSee('Manifest Required')->assertViewHas('canDecideClearance', false);
        Livewire::test(ListFiledRpusDepartures::class)->assertCanSeeTableRecords([$flight])->assertTableColumnStateSet('avsec_status', 'Manifest Required', $flight);
        foreach (['grant', 'deny'] as $decision) {
            $this->post(route('flights.avsec-clearance', $flight), $this->decision($flight, $decision))->assertSessionHasErrors();
        }
        $this->assertFalse($flight->avsecVerification()->exists());
        $this->assertSame(0, $flight->avsecDenials()->count());
    }

    public static function missingDocuments(): array
    {
        return [['missing'], ['incomplete']];
    }

    public function test_denial_requires_reason_confirmation_and_current_review(): void
    {
        $flight = $this->readyFlight();
        $this->officer();
        $data = $this->decision($flight, 'deny');
        $this->post(route('flights.avsec-clearance', $flight), [...$data, 'reason' => '   '])->assertSessionHasErrors('reason');
        $this->post(route('flights.avsec-clearance', $flight), [...$data, 'confirmation' => '0'])->assertSessionHasErrors('confirmation');
        foreach (['grant', 'deny'] as $decision) {
            $this->post(route('flights.avsec-clearance', $flight), [...$data, 'decision' => $decision, 'reviewed_fingerprint' => str_repeat('0', 64)])->assertSessionHasErrors('avsec');
        }
        $this->assertSame(0, $flight->avsecDenials()->count());
        $this->assertFalse($flight->avsecVerification()->exists());
    }

    public function test_unauthorized_decisions_are_forbidden(): void
    {
        $flight = $this->readyFlight();
        foreach ([['role' => UserRole::Atmo], ['station' => 'RPLL'], ['is_active' => false], ['role' => UserRole::Pilot]] as $attributes) {
            $this->officer($attributes);
            foreach (['grant', 'deny'] as $decision) {
                $this->post(route('flights.avsec-clearance', $flight), $this->decision($flight, $decision))->assertForbidden();
            }
        }
        $other = $this->readyFlight();
        $other->update(['departure_aerodrome' => 'RPLL']);
        $this->officer();
        $this->post(route('flights.avsec-clearance', $other), $this->decision($other, 'deny'))->assertForbidden();
        $this->assertSame(0, FlightPlanEvent::where('event_type', FlightPlanEvent::TYPE_AVSEC_DENIED)->count());
    }

    public function test_denial_audit_failure_leaves_no_clearance_record(): void
    {
        $flight = $this->readyFlight();
        $this->officer();
        $data = $this->decision($flight, 'deny');
        DB::unprepared("CREATE TRIGGER fail_avsec_denial BEFORE INSERT ON flight_plan_events WHEN NEW.event_type = 'avsec_denied' BEGIN SELECT RAISE(ABORT, 'Simulated denial audit failure'); END");
        $this->withoutExceptionHandling();
        try {
            $this->post(route('flights.avsec-clearance', $flight), $data);
            $this->fail('The audit write must succeed.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('Simulated denial audit failure', $exception->getMessage());
        } finally {
            DB::unprepared('DROP TRIGGER fail_avsec_denial');
        }
        $this->assertSame(0, $flight->avsecDenials()->count());
        $this->assertFalse($flight->avsecVerification()->exists());
        $this->assertSame('For AVSEC Clearance', FlightStatusDisplay::avsecBadge($flight->fresh())['label']);
    }
}
