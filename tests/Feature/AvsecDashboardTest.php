<?php

namespace Tests\Feature;

use App\Domain\FlightPlans\Enums\FlightPlanStatus;
use App\Domain\FlightPlans\Services\AvsecDepartureQuery;
use App\Domain\FlightPlans\Services\AvsecOperationalFlightQuery;
use App\Domain\FlightPlans\Services\AvsecVerificationService;
use App\Domain\FlightPlans\Services\FilingAuthorizationService;
use App\Domain\FlightPlans\Services\FlightManifestService;
use App\Domain\FlightPlans\Services\FlightPlanMutationService;
use App\Domain\FlightPlans\Services\FlightPlanQrPayloadService;
use App\Domain\FlightPlans\Support\FlightStatusDisplay;
use App\Domain\Users\Enums\UserRole;
use App\Filament\Panels\Avsec\Pages\Dashboard;
use App\Filament\Panels\Avsec\Pages\ImportScanQr;
use App\Filament\Panels\Avsec\Resources\AvsecVerifications\AvsecVerificationResource;
use App\Filament\Panels\Avsec\Resources\AvsecVerifications\Pages\ListAvsecVerifications;
use App\Filament\Panels\Avsec\Resources\FiledRpusDepartures\FiledRpusDepartureResource;
use App\Filament\Panels\Avsec\Resources\FiledRpusDepartures\Pages\ListFiledRpusDepartures;
use App\Filament\Panels\Avsec\Widgets\AllFlights;
use App\Models\Flight;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AvsecDashboardTest extends TestCase
{
    use RefreshDatabase, \Tests\Support\SignsFlightDocuments;

    private function officer(array $attributes = []): User
    {
        $officer = User::factory()->create(['role' => UserRole::Avsec, 'station' => 'RPUS', ...$attributes]);
        $this->actingAs($officer);
        Filament::setCurrentPanel(Filament::getPanel('avsec'));

        return $officer;
    }

    private function readyFlight(array $attributes = []): Flight
    {
        $flight = Flight::factory()->create($attributes)->refresh();
        app(FlightManifestService::class)->save($flight, $flight->filedBy, [
            'aircraft_owner_name' => 'Owner', 'aircraft_owner_contact_number' => '1234567',
            'home_base_hangar' => 'RPUS', 'coa_expiry_date' => now()->addYear()->toDateString(),
            'total_cargo_weight' => 0,
            'persons' => [['full_name' => $flight->pilot_in_command, 'nationality' => 'PH', 'classification' => 'pic']],
        ]);
        $this->signFlightDocuments($flight);

        return $flight->refresh();
    }

    public function test_sidebar_lists_separate_uncleared_departures_and_shared_cleared_flights(): void
    {
        $awaiting = $this->readyFlight();
        $verified = $this->readyFlight(['aircraft_identification' => 'RPC999']);
        $this->confirmFlightAvsec($verified);
        $this->officer();
        $this->get('/avsec')->assertOk()->assertSeeInOrder(['Dashboard', 'Filed RPUS Departures', 'Cleared Flights', 'Scan QR'])
            ->assertDontSee('Flight Operations')->assertDontSee('Eligible departures awaiting AVSEC clearance.')->assertDontSeeLivewire(ListFiledRpusDepartures::class)
            ->assertSeeLivewire(AllFlights::class);
        $navigation = collect(Filament::getNavigation())->flatMap(fn ($group) => $group->getItems())->map(fn ($item) => $item->getLabel())->values()->all();
        $this->assertSame(['Dashboard', 'Filed RPUS Departures', 'Cleared Flights', 'Scan QR'], $navigation);
        $this->assertSame(Heroicon::OutlinedSquares2x2, Dashboard::getNavigationIcon());
        $this->assertSame(Heroicon::OutlinedPaperAirplane, FiledRpusDepartureResource::getNavigationIcon());
        $this->assertSame(Heroicon::OutlinedShieldCheck, AvsecVerificationResource::getNavigationIcon());
        $this->assertSame(Heroicon::OutlinedQrCode, ImportScanQr::getNavigationIcon());
        $this->assertSame(1, count(array_filter(Filament::getWidgets(), fn ($widget) => $widget === AllFlights::class)));
        $this->get(FiledRpusDepartureResource::getUrl(panel: 'avsec'))->assertOk()->assertSee('<aside', false)->assertSee('fi-sidebar-header', false);
        Livewire::test(ListFiledRpusDepartures::class)->assertCanSeeTableRecords([$awaiting])->assertCanNotSeeTableRecords([$verified])
            ->sortTable('proposed_time', 'desc')->assertCanSeeTableRecords([$awaiting])
            ->filterTable('destination_aerodrome', 'RPLL')->assertCanSeeTableRecords([$awaiting])
            ->searchTable($awaiting->aircraft_identification)->assertCanSeeTableRecords([$awaiting]);
        $this->officer();
        $record = $verified->avsecVerification;
        Livewire::test(ListAvsecVerifications::class)->assertCanSeeTableRecords([$record])
            ->assertTableColumnStateSet('confirmation_status', 'Clearance Granted', $record)
            ->assertDontSee('Frozen')->assertDontSee('FROZEN');
        $clearedTable = Livewire::test(ListAvsecVerifications::class)->instance()->getTable();
        $this->assertSame(AvsecVerificationResource::getUrl('view', ['record' => $record], panel: 'avsec'), $clearedTable->getRecordUrl($record));
        $this->assertTrue($clearedTable->shouldOpenRecordUrlInNewTab($record));
        $this->assertSame('Cleared Flights', $clearedTable->getHeading());
        $this->assertSame('Flights cleared by PNP AVSEC, including verification records from previous shifts.', $clearedTable->getDescription());
        $this->assertSame('success', $clearedTable->getColumn('confirmation_status')->record($record)->getColor('Clearance Granted'));
        $this->get(AvsecVerificationResource::getUrl(panel: 'avsec'))->assertOk()
            ->assertSee('Flights cleared by PNP AVSEC, including verification records from previous shifts.');
        $this->assertSame(['CALLSIGN', 'DATE', 'PTD', 'Pilot', 'AVSEC STATUS'], array_values(array_map(fn ($column) => $column->getLabel(), $clearedTable->getColumns())));
        $this->get(AvsecVerificationResource::getUrl('view', ['record' => $record], panel: 'avsec'))
            ->assertOk()->assertSee('<aside', false)->assertSee('fi-sidebar-header', false)->assertSee($record->verifier_name)
            ->assertSee('Decision time (Asia/Manila)')->assertDontSee('frozen');
    }

    #[DataProvider('ineligibleFlights')]
    public function test_monitoring_keeps_incomplete_records_while_scanner_requires_current_documents(string $kind): void
    {
        $flight = $this->readyFlight();
        match ($kind) {
            'unsigned_plan' => $flight->update(['route' => 'CHANGED']),
            'unsigned_manifest' => $flight->manifest->update(['cargo_type' => 'CHANGED']),
            'missing_manifest' => $flight->manifest->delete(),
            'other_station' => $flight->update(['departure_aerodrome' => 'RPLL']),
            'archived' => $flight->forceFill(['pic_authorization_archived_at' => now()])->saveQuietly(),
            'rejected' => $flight->forceFill(['status' => FlightPlanStatus::Rejected])->saveQuietly(),
            'expired' => $flight->forceFill(['date_of_flight' => now()->subDays(2)->toDateString()])->saveQuietly(),
        };
        $officer = $this->officer();
        $this->assertSame(in_array($kind, ['unsigned_plan', 'unsigned_manifest', 'missing_manifest'], true), app(AvsecDepartureQuery::class)->forOfficer($officer)->whereKey($flight)->exists());
        $this->get(ImportScanQr::getUrl(['flight' => $flight->id], panel: 'avsec'))->assertNotFound();
    }

    public static function ineligibleFlights(): array
    {
        return array_map(fn ($kind) => [$kind], ['unsigned_plan', 'unsigned_manifest', 'missing_manifest', 'other_station', 'archived', 'rejected', 'expired']);
    }

    public function test_dashboard_review_confirms_through_existing_service_without_scanning(): void
    {
        $flight = $this->readyFlight();
        $officer = $this->officer();
        $this->get(ImportScanQr::getUrl(['flight' => $flight->id], panel: 'avsec'))
            ->assertOk()->assertSee('class="fi-layout"', false)->assertSee('<aside', false)->assertSee('fi-sidebar-header', false)
            ->assertSee('fi-sidebar-header', false)->assertSee('Cleared Flights')->assertSee($flight->aircraft_identification);
        $this->get(ImportScanQr::getUrl(['flight' => $flight->id], panel: 'avsec'))
            ->assertOk()->assertSee('<aside', false)->assertSee($flight->pilot_in_command);
        Livewire::withQueryParams(['flight' => $flight->id])->test(ImportScanQr::class)
            ->assertSet('matchedFlight.id', $flight->id)->assertSee($flight->pilot_in_command)
            ->set('clearanceConfirmed', true)->call('confirmClearance')->assertHasNoErrors()->assertSet('matchedFlight.verified', true);
        $this->assertSame($officer->id, $flight->avsecVerification()->sole()->verifier_user_id);
        $this->assertSame(1, $flight->events()->where('event_type', 'avsec_verified')->count());
    }

    public function test_review_rejects_manifest_changed_and_reauthorized_after_opening(): void
    {
        $flight = $this->readyFlight();
        $this->officer();
        $page = Livewire::withQueryParams(['flight' => $flight->id])->test(ImportScanQr::class);
        $flight->manifest->update(['cargo_type' => 'Updated cargo']);
        $packages = app(FilingAuthorizationService::class);
        $pilot = $flight->pilotInCommandUser;
        $packages->decide($packages->request($flight), $flight, $pilot, true, null, $flight->filingAuthorizations()->whereNotNull('pilot_signature_id')->latest('id')->firstOrFail()->pilotSignature);
        $flight->refresh()->forceFill(['status' => FlightPlanStatus::Pending])->saveQuietly();
        $page->set('clearanceConfirmed', true)->call('confirmClearance')->assertHasErrors();
        $this->assertFalse($flight->isAvsecFrozen());
    }

    public function test_station_and_role_are_enforced_on_dashboard_review_and_confirmation(): void
    {
        $flight = $this->readyFlight();
        foreach ([['station' => 'RPLL'], ['role' => UserRole::Atmo], ['is_active' => false]] as $attributes) {
            $officer = $this->officer($attributes);
            $this->assertSame(0, app(AvsecDepartureQuery::class)->forOfficer($officer)->count());
            $this->assertSame(0, app(AvsecOperationalFlightQuery::class)->forOfficer($officer)->count());
            $this->get(ImportScanQr::getUrl(['flight' => $flight->id], panel: 'avsec'))->assertForbidden();
            $this->get(FiledRpusDepartureResource::getUrl(panel: 'avsec'))->assertForbidden();
            $this->get(AvsecVerificationResource::getUrl(panel: 'avsec'))->assertForbidden();
        }
    }

    public function test_local_departure_includes_date_rollover(): void
    {
        $flight = new Flight(['date_of_flight' => '2026-11-09', 'proposed_time' => '2330']);
        $this->assertSame('2026-11-10 07:30', FiledRpusDepartureResource::departureTime($flight));
    }

    public function test_avsec_content_headings_are_hidden_without_removing_the_panel_shell(): void
    {
        $this->officer();
        foreach ([Dashboard::class, ListFiledRpusDepartures::class, ListAvsecVerifications::class, ImportScanQr::class] as $page) {
            $this->assertNull(app($page)->getHeading());
            $this->get($page::getUrl(panel: 'avsec'))->assertOk()->assertSee('class="fi-layout"', false)
                ->assertSee('<aside', false)->assertSee('fi-sidebar-header', false)->assertDontSee('class="fi-header-heading"', false);
        }
        $this->assertNotNull(app(\Filament\Pages\Dashboard::class)->getHeading());
    }

    public function test_all_flights_uses_an_inclusive_utc_window_across_midnight_and_preserves_visibility(): void
    {
        $this->travelTo(CarbonImmutable::parse('2030-01-09 01:00:00', 'UTC'));
        $visible = collect([
            ['2030-01-08', '0100'], ['2030-01-08', '2359'], ['2030-01-09', '0100'],
            ['2030-01-10', '0000'], ['2030-01-10', '0100'],
        ])->map(fn ($schedule, $index) => Flight::factory()->create([
            'date_of_flight' => $schedule[0], 'proposed_time' => $schedule[1], 'aircraft_identification' => 'RPV'.$index,
        ]));
        $hidden = collect([
            ['date_of_flight' => '2030-01-08', 'proposed_time' => '0059', 'revised_eobt' => '2300'],
            ['date_of_flight' => '2030-01-10', 'proposed_time' => '0101', 'revised_eobt' => '0000'],
            ['date_of_flight' => '2030-01-09', 'proposed_time' => '0100', 'departure_aerodrome' => 'RPLL'],
            ['date_of_flight' => '2030-01-09', 'proposed_time' => null],
        ])->map(fn ($attributes) => Flight::factory()->create($attributes));
        $this->officer();
        $page = Livewire::test(AllFlights::class)->assertCanSeeTableRecords($visible)->assertCanNotSeeTableRecords($hidden)
            ->assertTableColumnFormattedStateSet('date_of_flight', '2030-01-08', $visible[0])
            ->assertTableColumnFormattedStateSet('proposed_time', '0100Z', $visible[0])
            ->sortTable('proposed_time', 'desc')->assertCanSeeTableRecords($visible)
            ->searchTable('RPV0')->assertCanSeeTableRecords([$visible[0]])->assertCanNotSeeTableRecords([$visible[1]]);
        $this->assertSame(['CALLSIGN', 'DATE', 'PTD', 'Pilot', 'AVSEC STATUS'], array_values(array_map(fn ($column) => $column->getLabel(), $page->instance()->getTable()->getColumns())));
        $this->assertSame(route('flights.view', $visible[0]), $page->instance()->getTable()->getRecordUrl($visible[0]));
        $this->assertTrue($page->instance()->getTable()->shouldOpenRecordUrlInNewTab($visible[0]));
    }

    public function test_clearance_status_remains_separate_from_atmo_rejection_and_lifecycle(): void
    {
        $this->travelTo(CarbonImmutable::parse('2030-01-01 14:30:00', 'UTC'));
        $awaiting = $this->readyFlight(['date_of_flight' => '2030-01-01']);
        $cleared = $this->readyFlight(['date_of_flight' => '2030-01-01']);
        $this->confirmFlightAvsec($cleared);
        $this->officer();
        $allFlights = Livewire::test(AllFlights::class)->assertTableColumnStateSet('avsec_status', 'Pending Clearance', $awaiting)
            ->assertTableColumnStateSet('avsec_status', 'Clearance Granted', $cleared)->assertDontSee('Frozen');
        $statusColumn = $allFlights->instance()->getTable()->getColumn('avsec_status')->record($cleared->fresh());
        $this->assertSame('success', $statusColumn->getColor('Clearance Granted'));
        $this->assertSame('cleared', $statusColumn->getExtraCellAttributes()['data-flight-status']);
        $this->assertSame(FlightPlanStatus::Pending, $cleared->fresh()->status);
        $this->assertSame('success', FlightStatusDisplay::avsecBadge($cleared)['color']);
        $awaiting->loadMissing('manifest.persons', 'filingAuthorizations.pilotSignature', 'avsecVerification');
        $this->assertSame('warning', FlightStatusDisplay::avsecBadge($awaiting)['color']);
        $atmo = User::factory()->create(['role' => UserRole::Atmo, 'station' => 'RPUS']);
        app(FlightPlanMutationService::class)->reject($cleared, $atmo, 'AT', 'Review needed');
        $badge = FlightStatusDisplay::avsecBadge($cleared->fresh());
        $this->assertSame('Clearance Granted', $badge['label']);
        $this->assertSame('success', $badge['color']);
        $rejectedPage = Livewire::test(AllFlights::class)->assertTableColumnStateSet('avsec_status', 'Clearance Granted', $cleared);
        $this->assertSame('success', $rejectedPage->instance()->getTable()->getColumn('avsec_status')->record($cleared->fresh())->getColor('Clearance Granted'));
        Livewire::test(ListAvsecVerifications::class)->assertTableColumnStateSet('confirmation_status', 'Clearance Granted', $cleared->avsecVerification()->sole());
        $this->assertTrue($cleared->fresh()->isAvsecFrozen());
    }

    public function test_departures_have_operational_columns_and_preview_links(): void
    {
        $flight = $this->readyFlight();
        $this->officer();
        $page = Livewire::test(ListFiledRpusDepartures::class)
            ->assertTableColumnStateSet('avsec_status', 'Pending Clearance', $flight)->assertDontSee('Frozen')
            ->assertSee('All dates and PTD are UTC (Z).', escape: false)
            ->assertSee(route('flights.view', $flight), false);
        $table = $page->instance()->getTable();
        $this->assertSame(['CALLSIGN', 'DATE', 'PTD', 'Pilot', 'AVSEC STATUS'], array_values(array_map(fn ($column) => $column->getLabel(), $table->getColumns())));
        $this->assertSame(route('flights.view', $flight), $table->getRecordUrl($flight));
        $this->assertTrue($table->shouldOpenRecordUrlInNewTab($flight));
        $this->assertSame('warning', $table->getColumn('avsec_status')->record($flight)->getColor('Pending Clearance'));
        $this->assertFalse($flight->isAvsecFrozen());
    }

    #[DataProvider('utcDepartures')]
    public function test_departure_table_preserves_utc_date_and_four_digit_ptd(string $time, string $expected): void
    {
        $flight = $this->readyFlight(['date_of_flight' => '2030-01-01', 'proposed_time' => $time, 'revised_eobt' => '1200']);
        $this->officer();
        Livewire::test(ListFiledRpusDepartures::class)->assertTableColumnFormattedStateSet('date_of_flight', '2030-01-01', $flight)
            ->assertTableColumnStateSet('proposed_time', $expected, $flight);
        $this->assertSame('2030-01-01', $flight->fresh()->date_of_flight);
        $this->confirmFlightAvsec($flight);
        $record = $flight->avsecVerification()->sole();
        Livewire::test(ListAvsecVerifications::class)->assertTableColumnFormattedStateSet('flight.date_of_flight', '2030-01-01', $record)
            ->assertTableColumnFormattedStateSet('flight.proposed_time', $expected, $record);
    }

    public static function utcDepartures(): array
    {
        return [['0000', '0000Z'], ['0130', '0130Z'], ['2359', '2359Z']];
    }

    public function test_qr_entry_retains_panel_shell_and_loads_the_same_review(): void
    {
        $flight = $this->readyFlight();
        $this->officer();
        $this->get(ImportScanQr::getUrl(panel: 'avsec'))->assertOk()->assertSee('<aside', false)->assertSee('fi-sidebar-header', false);
        Livewire::test(ImportScanQr::class)->set('payload', app(FlightPlanQrPayloadService::class)->buildPayload($flight))
            ->call('submit')->assertHasNoErrors()->assertSet('matchedFlight.id', $flight->id);
        $this->assertFalse($flight->isAvsecFrozen());
    }

    public function test_review_fingerprint_rejects_changed_documents_even_with_a_current_qr(): void
    {
        $flight = $this->readyFlight();
        $officer = $this->officer();
        $service = app(AvsecVerificationService::class);
        $fingerprint = $service->reviewFingerprint($flight);
        $flight->manifest->update(['cargo_type' => 'Updated cargo']);
        $packages = app(FilingAuthorizationService::class);
        $pilot = $flight->pilotInCommandUser;
        $packages->decide($packages->request($flight), $flight, $pilot, true, null, $flight->filingAuthorizations()->whereNotNull('pilot_signature_id')->latest('id')->firstOrFail()->pilotSignature);
        $flight->refresh()->forceFill(['status' => FlightPlanStatus::Pending])->saveQuietly();
        $payload = app(FlightPlanQrPayloadService::class)->buildPayload($flight->fresh());
        $this->assertSame(FlightPlanStatus::Pending, $flight->fresh()->status);
        $this->assertTrue($flight->fresh()->isPicAuthorizationCurrent(), 'Flight authorization must be current in the stale-review fixture.');
        $this->assertTrue($packages->isReadyForAtmo($flight->fresh()), 'Both document signatures must be current in the stale-review fixture.');
        try {
            $service->confirm($payload, $officer, true, reviewedFingerprint: $fingerprint);
            $this->fail('A changed document must require a fresh review.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('reviewed documents have changed', $exception->errors()['avsec'][0]);
        }
        $this->assertFalse($flight->isAvsecFrozen());
    }
}
