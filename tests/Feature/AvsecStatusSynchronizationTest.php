<?php

namespace Tests\Feature;

use App\Domain\FlightPlans\Enums\AvsecClearanceState;
use App\Domain\FlightPlans\Enums\FlightPlanStatus;
use App\Domain\FlightPlans\Services\AvsecVerificationService;
use App\Domain\FlightPlans\Services\FilingAuthorizationService;
use App\Domain\FlightPlans\Services\FlightManifestService;
use App\Domain\FlightPlans\Services\FlightPlanPdfService;
use App\Domain\FlightPlans\Services\FlightPlanQrPayloadService;
use App\Domain\FlightPlans\Support\FlightStatusDisplay;
use App\Domain\Users\Enums\UserRole;
use App\Filament\Panels\Atmo\Pages\ImportScanQr as AtmoScanner;
use App\Filament\Panels\Atmo\Resources\AllFlightPlans\Pages\ListAllFlights;
use App\Filament\Panels\Atmo\Resources\AwaitingAvsecFlights\Pages\ListAwaitingAvsecFlights;
use App\Filament\Panels\Atmo\Resources\Flights\FlightResource as AtmoFlightResource;
use App\Filament\Panels\Atmo\Resources\Flights\Pages\ListFlights;
use App\Filament\Panels\Pilot\Pages\ScanAuthorizationQr as PilotScanner;
use App\Filament\Panels\Pilot\Resources\MyCurrentFlights\Pages\ListMyCurrentFlights;
use App\Filament\Panels\Pilot\Widgets\PilotDashboardWidget;
use App\Models\Flight;
use App\Models\Operator;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\SignsFlightDocuments;
use Tests\TestCase;

class AvsecStatusSynchronizationTest extends TestCase
{
    use RefreshDatabase, SignsFlightDocuments;

    private function readyFlight(): Flight
    {
        $operator = Operator::factory()->create();
        $pilot = User::factory()->create(['role' => UserRole::Pilot, 'operator_id' => $operator->id]);
        $flight = Flight::factory()->create([
            'operator_id' => $operator->id, 'filed_by_user_id' => $pilot->id,
            'prepared_by_user_id' => $pilot->id, 'pilot_in_command_user_id' => $pilot->id,
        ])->refresh();
        app(FlightManifestService::class)->save($flight, $pilot, [
            'aircraft_owner_name' => 'Owner', 'aircraft_owner_contact_number' => '1234567',
            'home_base_hangar' => 'RPUS', 'coa_expiry_date' => now()->addYear()->toDateString(), 'total_cargo_weight' => 0,
            'persons' => [['full_name' => $flight->pilot_in_command, 'nationality' => 'PH', 'classification' => 'pic']],
        ]);
        $this->signFlightDocuments($flight);

        return $flight->refresh();
    }

    private function deny(Flight $flight): void
    {
        $officer = User::factory()->create(['role' => UserRole::Avsec, 'station' => 'RPUS']);
        app(AvsecVerificationService::class)->deny(
            app(FlightPlanQrPayloadService::class)->buildPayload($flight->fresh()), $officer, 'Offline clearance denied',
        );
    }

    #[DataProvider('clearanceStates')]
    public function test_current_clearance_is_rendered_across_pilot_atmo_and_scanners(string $decision, string $label, string $color): void
    {
        $flight = $this->readyFlight();
        if ($decision === 'granted') {
            $this->confirmFlightAvsec($flight);
        } elseif ($decision === 'denied') {
            $this->deny($flight);
        }
        $flight->refresh();
        $before = $flight->only(['status', 'revision_number', 'pic_authorization_status', 'pic_authorized_by_user_id',
            'pic_authorized_at', 'pic_authorized_revision', 'pic_authorization_token', 'pic_authorization_token_expires_at']);
        $manifestBefore = $flight->manifest->getAttributes();
        $payload = app(FlightPlanQrPayloadService::class)->buildPayload($flight);
        $pilot = $flight->filedBy;
        $this->actingAs($pilot);
        Filament::setCurrentPanel(Filament::getPanel('pilot'));
        $pilotPage = Livewire::test(ListMyCurrentFlights::class)->assertCanSeeTableRecords([$flight])
            ->assertTableColumnStateSet('status_presentation', $label, $flight)->assertSee($label);
        $this->assertSame($color, $pilotPage->instance()->getTable()->getColumn('status_presentation')->record($flight)->getColor($label));
        Livewire::test(PilotDashboardWidget::class)->assertSee($label);
        $this->get('/pilot/my-current-flights')->assertOk()->assertSee($label);
        $this->get(route('flights.view', $flight))->assertOk()->assertSee($label)->assertSee('data-avsec-preview-color="'.$color.'"', false);
        Livewire::test(PilotScanner::class)->set('payload', $payload)->call('submit')->assertHasNoErrors()
            ->assertSet('matchedFlight.status_label', $label)->assertSet('matchedFlight.status_color', $color)
            ->assertSet('picAuthorizationHandoffToken', null)->assertSet('matchedFlight.view_url', route('flights.view', $flight))
            ->assertSee($label)->assertDontSee('Authorize as PIC');
        $atmo = User::factory()->create(['role' => UserRole::Atmo, 'station' => 'RPUS']);
        $this->actingAs($atmo);
        Filament::setCurrentPanel(Filament::getPanel('atmo'));
        Livewire::test(ListAllFlights::class)->assertTableColumnStateSet('status_presentation', $label, $flight)
            ->assertSee($label)->assertSee('fi-color-'.$color, false);
        $queue = $decision !== 'awaiting' ? ListFlights::class : ListAwaitingAvsecFlights::class;
        Livewire::test($queue)->assertCanSeeTableRecords([$flight])->assertTableColumnStateSet('status_presentation', $label, $flight)->assertSee($label);
        $this->assertSame($decision !== 'awaiting', AtmoFlightResource::getEloquentQuery()->whereKey($flight)->exists());
        $this->assertSame($decision === 'granted', $atmo->can('accept', $flight));
        $this->get(route('flights.view', $flight))->assertOk()->assertSee($label)->assertSee('data-avsec-preview-color="'.$color.'"', false);
        Livewire::test(AtmoScanner::class)->set('payload', $payload)->call('submit')->assertHasNoErrors()
            ->assertSet('matchedFlight.status_label', $label)->assertSet('matchedFlight.status_color', $color)->assertSee($label);
        $this->post(route('flightplan.scan-qr.lookup'), ['payload' => $payload])->assertOk()->assertSee($label)
            ->assertViewHas('matchedFlight', fn (array $match) => $match['status_label'] === $label && $match['status_color'] === $color);
        $this->assertSame($before, $flight->fresh()->only(array_keys($before)));
        $this->assertSame($manifestBefore, $flight->manifest->fresh()->getAttributes());
        $this->assertSame(FlightPlanStatus::Pending, $flight->fresh()->status);
        $officer = User::factory()->create(['role' => UserRole::Avsec, 'station' => 'RPUS']);
        $this->actingAs($officer);
        Filament::setCurrentPanel(Filament::getPanel('avsec'));
        $clearanceColor = $decision === 'granted' ? 'success' : $color;
        $avsecLabel = match ($decision) {
            'granted' => 'Clearance Granted', 'denied' => 'Clearance Denied', default => 'For AVSEC Clearance',
        };
        $this->get(route('flights.view', $flight))->assertOk()->assertSee($avsecLabel)
            ->assertViewHas('avsecStatus', fn (array $status) => $status['label'] === $avsecLabel && $status['color'] === $clearanceColor);
    }

    public static function clearanceStates(): array
    {
        return [['awaiting', 'For AVSEC Clearance', 'warning'], ['granted', 'For ATMO Signature', 'gray'], ['denied', 'AVSEC Denied', 'danger']];
    }

    public function test_denial_is_fresh_after_reopening_and_applies_only_to_its_current_revision(): void
    {
        $flight = $this->readyFlight();
        $flight->load('avsecVerification', 'avsecDenials');
        $service = app(AvsecVerificationService::class);
        $this->assertSame(AvsecClearanceState::Awaiting, $service->currentState($flight));
        $this->actingAs($flight->filedBy);
        Filament::setCurrentPanel(Filament::getPanel('pilot'));
        $payload = app(FlightPlanQrPayloadService::class)->buildPayload($flight);
        $scanner = Livewire::test(PilotScanner::class)->set('payload', $payload)->call('submit')
            ->assertSet('matchedFlight.status_label', 'For AVSEC Clearance');
        $this->deny($flight);
        $this->assertSame(AvsecClearanceState::Denied, $service->currentState($flight));
        $scanner->call('submit')->assertSet('matchedFlight.status_label', 'AVSEC Denied');
        foreach (range(1, 2) as $attempt) {
            Livewire::test(ListMyCurrentFlights::class)->assertTableColumnStateSet('status_presentation', 'AVSEC Denied', $flight);
            $this->get(route('flights.view', $flight))->assertOk()->assertSee('AVSEC Denied');
        }
        $revision = $flight->revision_number;
        $flight->manifest->update(['cargo_type' => 'Revised cargo']);
        $flight->refresh();
        $this->assertGreaterThan($revision, $flight->revision_number);
        $this->assertSame(AvsecClearanceState::Awaiting, $service->currentState($flight));
        $this->assertSame(1, $flight->avsecDenials()->count());
        $this->assertNotSame('AVSEC Denied', FlightStatusDisplay::badge($flight, UserRole::Pilot)['label']);
        $signature = $flight->filingAuthorizations()->whereNotNull('pilot_signature_id')->latest('id')->firstOrFail()->pilotSignature;
        $packages = app(FilingAuthorizationService::class);
        $packages->decide($packages->request($flight), $flight, $flight->filedBy, true, null, $signature);
        $flight->refresh()->forceFill(['status' => FlightPlanStatus::Pending])->saveQuietly();
        $this->assertSame('For AVSEC Clearance', FlightStatusDisplay::badge($flight->refresh(), UserRole::Pilot)['label']);
    }

    public function test_current_grant_requires_matching_revision_and_untampered_evidence(): void
    {
        $flight = $this->readyFlight();
        $this->confirmFlightAvsec($flight);
        $service = app(AvsecVerificationService::class);
        $this->assertSame(AvsecClearanceState::Granted, $service->currentState($flight));
        $newRevision = clone $flight;
        $newRevision->revision_number++;
        $this->assertSame(AvsecClearanceState::Awaiting, $service->currentState($newRevision));
        $pdfs = app(FlightPlanPdfService::class);
        Storage::disk('local')->put($pdfs->storedPaths($flight)->firstOrFail(), 'tampered');
        $this->assertSame(AvsecClearanceState::Awaiting, $service->currentState($flight));
        $this->assertSame(1, $flight->avsecVerification()->count());
    }

    public function test_scanners_and_previews_preserve_operator_isolation_and_allow_unsigned_atmo_preview(): void
    {
        $flight = $this->readyFlight();
        $this->deny($flight);
        $otherPilot = User::factory()->create(['role' => UserRole::Pilot, 'operator_id' => Operator::factory()->create()->id]);
        $this->actingAs($otherPilot);
        Filament::setCurrentPanel(Filament::getPanel('pilot'));
        Livewire::test(PilotScanner::class)->set('payload', app(FlightPlanQrPayloadService::class)->buildPayload($flight))->call('submit')
            ->assertSet('matchedFlight', null);
        Livewire::test(ListMyCurrentFlights::class)->assertCanNotSeeTableRecords([$flight]);
        $this->get(route('flights.view', $flight))->assertForbidden();
        $atmo = User::factory()->create(['role' => UserRole::Atmo, 'station' => 'RPUS']);
        $unsigned = Flight::factory()->create();
        $this->actingAs($atmo);
        Filament::setCurrentPanel(Filament::getPanel('atmo'));
        Livewire::test(AtmoScanner::class)->set('payload', app(FlightPlanQrPayloadService::class)->buildPayload($unsigned))->call('submit')
            ->assertSet('matchedFlight.id', $unsigned->id);
        $this->get(route('flights.view', $unsigned))->assertOk()->assertViewHas('canAcceptFlightPlan', false);
        $this->assertFalse($atmo->can('accept', $flight));
        $this->assertSame(FlightPlanStatus::Pending, $flight->fresh()->status);
    }
}
