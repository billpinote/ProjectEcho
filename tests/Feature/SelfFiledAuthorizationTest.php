<?php

namespace Tests\Feature;

use App\Auth\AuthenticationEvidence;
use App\Domain\FlightPlans\Enums\FlightPlanStatus;
use App\Domain\FlightPlans\Services\FilingAuthorizationService;
use App\Domain\FlightPlans\Services\FlightManifestService;
use App\Domain\FlightPlans\Services\FlightPlanPdfService;
use App\Domain\FlightPlans\Services\FlightPlanSubmissionService;
use App\Domain\FlightPlans\Services\PicAuthorizationService;
use App\Domain\FlightPlans\Support\FilingDocumentRegistry;
use App\Domain\Pilots\Enums\PilotLicenseType;
use App\Domain\Users\Enums\UserRole;
use App\Filament\Panels\Pilot\Pages\EditFlightManifest;
use App\Filament\Panels\Pilot\Resources\Flights\Pages\CreateFlight;
use App\Models\Flight;
use App\Models\Operator;
use App\Models\PilotSignature;
use App\Models\User;
use App\Services\ProfileUpdates\ProfileUpdateRequestService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class SelfFiledAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        Filament::setCurrentPanel('pilot');
    }

    public function test_create_opens_confirmation_and_cancel_preserves_form_without_filing(): void
    {
        $pilot = $this->pilot();
        $this->signature($pilot);
        $page = Livewire::actingAs($pilot)->test(CreateFlight::class)->fillForm($this->data())
            ->call('create')->assertActionMounted('authorizeFiling');
        $action = $page->instance()->getMountedAction();
        $this->assertSame('Create Flight Plan', $action->getModalHeading());
        $this->assertStringContainsString('No PIC signature is applied', $action->getModalDescription());
        $this->assertDatabaseCount('flights', 0);
        $this->assertDatabaseCount('filing_authorizations', 0);
        $page->call('unmountAction')->assertFormSet(['aircraft_identification' => 'RPC123']);
        $this->assertDatabaseCount('flights', 0);
    }

    public function test_confirmed_creation_saves_manifest_required_draft_without_signing(): void
    {
        $pilot = $this->pilot();
        $this->signature($pilot);
        Livewire::actingAs($pilot)->test(CreateFlight::class)->fillForm($this->data())
            ->call('create')->callMountedAction()->assertHasNoErrors()->assertRedirect();
        $flight = Flight::query()->sole();
        $this->assertSame(FlightPlanStatus::AwaitingPic, $flight->status);
        $this->assertSame('Manifest Required', $flight->documentSignatureStatus());
        $this->assertFalse($flight->isPicAuthorizationCurrent());
        $this->assertDatabaseCount('filing_authorizations', 0);
        $this->get(route('flights.view', $flight))->assertOk()->assertDontSee('alt="Authorized PIC signature"', false);
        $this->get(route('flights.pdf.download', $flight))->assertOk();
        $this->assertSame(0, $flight->events()->where('event_type', 'pic_authorized')->count());
    }

    public function test_manifest_submission_requires_one_explicit_complete_package_authorization(): void
    {
        $pilot = $this->pilot();
        $signature = $this->signature($pilot);
        $flight = $this->draft($pilot);
        $this->saveManifestFixture($flight, $pilot);
        $this->assertTrue(Flight::query()->currentForPilot()->whereKey($flight)->exists());
        $this->assertFalse($flight->fresh()->canSubmitToAtc());
        $this->assertDatabaseCount('filing_authorizations', 0);
        Livewire::actingAs($pilot)->withQueryParams(['confirm_signature' => 1])
            ->test(EditFlightManifest::class, ['flight' => $flight])->assertActionMounted('signFiling')
            ->callMountedAction()->assertHasNoErrors();
        $flight->refresh();
        $this->assertSame(FlightPlanStatus::Pending, $flight->status);
        $this->assertTrue($flight->canSubmitToAtc());
        $package = $flight->filingAuthorizations()->sole();
        $this->assertSame($signature->id, $package->pilot_signature_id);
        $this->assertCount(2, $package->pdf_documents);
    }

    public function test_missing_and_pending_signatures_allow_drafts_but_cannot_authorize(): void
    {
        $pilot = $this->pilot();
        Livewire::actingAs($pilot)->test(CreateFlight::class)->fillForm($this->data())
            ->call('create')->callMountedAction()->assertHasNoErrors();
        $flight = Flight::query()->sole();
        $this->saveManifestFixture($flight, $pilot);
        Livewire::actingAs($pilot)->test(EditFlightManifest::class, ['flight' => $flight])->assertActionDisabled('signFiling');
        PilotSignature::factory()->create(['user_id' => $pilot->id]);
        Livewire::actingAs($pilot)->test(EditFlightManifest::class, ['flight' => $flight])->assertActionDisabled('signFiling');
        $this->assertDatabaseCount('filing_authorizations', 0);
    }

    public function test_signature_integrity_is_rechecked_at_package_confirmation_and_saved_data_is_preserved(): void
    {
        $pilot = $this->pilot();
        $signature = $this->signature($pilot);
        $flight = $this->draft($pilot);
        $this->saveManifestFixture($flight, $pilot);
        $page = Livewire::actingAs($pilot)->test(EditFlightManifest::class, ['flight' => $flight])->mountAction('signFiling');
        Storage::disk('local')->put($signature->stored_path, 'tampered');
        try {
            app(PicAuthorizationService::class)->authorizeSelfFiling($flight, $pilot,
                AuthenticationEvidence::fromSession(Auth::guard()), $signature->id);
            $this->fail('Tampered signature was applied.');
        } catch (ValidationException) {
            $this->assertFalse($flight->fresh()->canSubmitToAtc());
        }
        $this->assertNotNull($flight->manifest()->first());
        $this->assertDatabaseCount('filing_authorizations', 0);
        $this->assertFalse($flight->fresh()->canSubmitToAtc());
    }

    public function test_form_changes_require_new_confirmation_without_signing_at_creation(): void
    {
        $pilot = $this->pilot();
        $this->signature($pilot);
        $page = Livewire::actingAs($pilot)->test(CreateFlight::class)->fillForm($this->data())->call('create');
        $page->set('data.route', 'DCT CHANGED')->callMountedAction()->assertHasErrors('data.signature');
        $this->assertDatabaseCount('flights', 0);
        $page->call('unmountAction')->call('create');
        $this->signature($pilot, 2);
        $page->callMountedAction()->assertHasNoErrors();
        $this->assertDatabaseCount('flights', 1);
        $this->assertDatabaseCount('filing_authorizations', 0);
    }

    public function test_replayed_submission_does_not_create_duplicate_flights_or_authorizations(): void
    {
        $pilot = $this->pilot();
        $signature = $this->signature($pilot);
        $this->actingAs($pilot);
        $evidence = AuthenticationEvidence::fromSession(Auth::guard());
        $service = app(FlightPlanSubmissionService::class);
        $first = $service->submitSelfPrepared($this->data(), $pilot, $evidence, 'test-submission');
        $second = $service->submitSelfPrepared($this->data(), $pilot, $evidence, 'test-submission');
        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('flights', 1);
        $this->assertDatabaseCount('filing_authorizations', 0);
        $this->expectException(ValidationException::class);
        $service->submitSelfPrepared([...$this->data(), 'route' => 'CHANGED'], $pilot, $evidence, 'test-submission');
    }

    public function test_profile_change_after_review_prevents_submission(): void
    {
        $pilot = $this->pilot();
        $this->signature($pilot);
        $page = Livewire::actingAs($pilot)->test(CreateFlight::class)->fillForm($this->data())->call('create');
        $pilot->pilotProfile()->update(['license_number' => 'UPDATED-AFTER-REVIEW']);
        $page->callMountedAction()->assertHasErrors('data.signature');
        $this->assertDatabaseCount('flights', 0);
        $this->assertDatabaseCount('filing_authorizations', 0);
    }

    public function test_legacy_session_submission_redirects_to_confirmation_without_creating_a_flight(): void
    {
        $pilot = $this->pilot();
        $this->actingAs($pilot)->withSession(['flight_plan_preview' => $this->data()])
            ->post(route('flightplan.approve'))->assertRedirectContains('from_preview=1')
            ->assertSessionHas('flight_plan_preview');
        $this->assertDatabaseCount('flights', 0);
        Livewire::actingAs($pilot)->withQueryParams(['from_preview' => 1])->test(CreateFlight::class)
            ->assertFormSet(['aircraft_identification' => 'RPC123']);
    }

    public function test_attached_manifest_shares_signature_and_historical_package_survives_revisions(): void
    {
        $pilot = $this->pilot();
        $signature = $this->signature($pilot);
        $this->actingAs($pilot);
        $flight = Flight::factory()->create([
            'status' => FlightPlanStatus::AwaitingPic, 'operator_id' => $pilot->operator_id,
            'filed_by_user_id' => $pilot->id, 'prepared_by_user_id' => $pilot->id,
            'pilot_in_command_user_id' => $pilot->id, 'persons_on_board' => 1,
            'pilot_in_command' => $pilot->fullName(),
        ])->refresh();
        app(FlightManifestService::class)->save($flight, $pilot, [
            'aircraft_owner_name' => 'TEST OWNER', 'aircraft_owner_contact_number' => '1234567',
            'home_base_hangar' => 'RPUS', 'coa_expiry_date' => now()->addYear()->toDateString(),
            'total_cargo_weight' => '0.000',
            'persons' => [['full_name' => $pilot->fullName(), 'nationality' => 'PH', 'classification' => 'pic']],
        ]);
        $registry = app(FilingDocumentRegistry::class);
        $fingerprint = $registry::fingerprint($registry->snapshots($flight));
        app(PicAuthorizationService::class)->authorizeSelfFiling($flight, $pilot,
            AuthenticationEvidence::fromSession(Auth::guard()), $signature->id, $fingerprint);
        $package = $flight->filingAuthorizations()->latest('id')->firstOrFail();
        $this->assertCount(2, $package->pdf_documents);
        foreach ($package->pdf_documents as $type => $pdf) {
            $unsigned = $registry->render($type, $package->authorized_documents, null);
            $this->assertGreaterThan(substr_count($unsigned, '/Subtype /Image'), substr_count(base64_decode($pdf['bytes']), '/Subtype /Image'));
        }
        $preserved = $package->pdf_documents;
        $this->signature($pilot, 2);
        $this->assertSame($signature->id, $package->fresh()->pilot_signature_id);
        $this->assertSame($preserved, $package->fresh()->pdf_documents);
        $flight->refresh()->update(['route' => 'DCT CHANGED']);
        $this->assertFalse($flight->refresh()->isPicAuthorizationCurrent());
        $this->assertSame(FlightPlanStatus::AwaitingPic, $flight->status);
        $this->assertNull(app(FilingAuthorizationService::class)->signatureBytes($flight));
        try {
            app(PicAuthorizationService::class)->authorizeSelfFiling($flight, $pilot,
                AuthenticationEvidence::fromSession(Auth::guard()), $pilot->fresh()->approvedSignature->id, $fingerprint);
            $this->fail('A stale document review was authorized.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('signature', $exception->errors());
            $this->assertSame(1, $flight->filingAuthorizations()->count());
        }
        $this->get(route('filing.document', ['package' => $package, 'type' => 'passenger_cargo_manifest']))
            ->assertOk()->assertContent(base64_decode($preserved['passenger_cargo_manifest']['bytes']));
    }

    public function test_pdf_render_failure_rolls_back_signature_decision_but_preserves_saved_filing(): void
    {
        $pilot = $this->pilot();
        $signature = $this->signature($pilot);
        $flight = $this->draft($pilot);
        $this->saveManifestFixture($flight, $pilot);
        $events = $flight->events()->count();
        $this->mock(FlightPlanPdfService::class)->shouldReceive('renderSnapshot')->andThrow(new \RuntimeException('Synthetic PDF failure'));
        try {
            app(PicAuthorizationService::class)->authorizeSelfFiling($flight, $pilot,
                AuthenticationEvidence::fromSession(Auth::guard()), $signature->id);
            $this->fail('Expected renderer failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Synthetic PDF failure', $exception->getMessage());
            $this->assertDatabaseCount('flights', 1);
            $this->assertDatabaseCount('filing_authorizations', 0);
            $this->assertSame($events, $flight->events()->count());
            $this->assertNull($flight->fresh()->pic_authorized_at);
        }
    }

    private function draft(User $pilot): Flight
    {
        $this->actingAs($pilot);

        return app(FlightPlanSubmissionService::class)->submitSelfPrepared($this->data(), $pilot,
            AuthenticationEvidence::fromSession(Auth::guard()), (string) Str::uuid());
    }

    private function saveManifestFixture(Flight $flight, User $pilot): void
    {
        app(FlightManifestService::class)->save($flight, $pilot, [
            'aircraft_owner_name' => 'Owner', 'aircraft_owner_contact_number' => '1234567', 'home_base_hangar' => 'RPUS',
            'coa_expiry_date' => now()->addYear()->toDateString(), 'total_cargo_weight' => 0,
            'persons' => [['full_name' => $flight->pilot_in_command, 'nationality' => 'PH', 'classification' => 'pic']],
        ]);
    }

    private function pilot(): User
    {
        $pilot = User::factory()->create(['role' => UserRole::Pilot, 'operator_id' => Operator::factory()->create()->id]);
        $pilot->pilotProfile()->create([
            'license_type' => PilotLicenseType::CommercialPilot, 'license_number' => 'TEST-123',
            'ratings' => 'C172', 'license_expiry_date' => now()->addYears(2)->toDateString(),
        ]);

        return $pilot;
    }

    private function signature(User $pilot, int $version = 1): PilotSignature
    {
        $signature = PilotSignature::factory()->create(['user_id' => $pilot->id, 'version' => $version]);
        app(ProfileUpdateRequestService::class)->approve($signature->request, User::factory()->create(['role' => UserRole::Admin]));

        return $signature->refresh();
    }

    private function data(): array
    {
        $date = now('Asia/Manila')->addDay();

        return [
            'date_of_flight' => $date->toDateString(), 'aircraft_identification' => 'RPC123',
            'flight_rules' => 'V', 'type_of_flight' => 'G', 'number' => '1', 'type_of_aircraft' => 'C172',
            'wake_turbulence_cat' => 'L', 'equipment_10a' => 'S', 'equipment_10b' => 'C',
            'departure_aerodrome' => 'RPUS', 'destination_aerodrome' => 'RPLL', 'proposed_time' => '1430',
            'cruising_speed' => 'N0100', 'level' => 'VFR', 'route' => 'DCT', 'total_eet' => '0100',
            'endurance' => '0300', 'persons_on_board' => '1', 'other_information' => 'DOF/'.$date->format('Ymd'),
            'dinghies_enabled' => false, 'authorized_representative_enabled' => false,
        ];
    }
}
