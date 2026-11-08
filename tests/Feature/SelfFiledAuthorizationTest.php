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
use App\Filament\Panels\Pilot\Resources\AwaitingAuthorizationFlights\AwaitingAuthorizationFlightResource;
use App\Filament\Panels\Pilot\Resources\Flights\Pages\CreateFlight;
use App\Models\Flight;
use App\Models\FlightPlanEvent;
use App\Models\Operator;
use App\Models\PilotSignature;
use App\Models\User;
use App\Services\ProfileUpdates\ProfileUpdateRequestService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
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
        $this->assertSame('Authorize Flight Filing', $action->getModalHeading());
        $this->assertStringContainsString('Documents included: Flight plan.', $action->getModalDescription());
        $this->assertDatabaseCount('flights', 0);
        $this->assertDatabaseCount('filing_authorizations', 0);
        $page->call('unmountAction')->assertFormSet(['aircraft_identification' => 'RPC123']);
        $this->assertDatabaseCount('flights', 0);
    }

    public function test_confirmed_submission_preserves_signature_evidence_and_signed_pdf(): void
    {
        $pilot = $this->pilot();
        $signature = $this->signature($pilot);
        Livewire::actingAs($pilot)->test(CreateFlight::class)->fillForm($this->data())
            ->call('create')->assertActionMounted('authorizeFiling')->callMountedAction()
            ->assertHasNoErrors()->assertRedirect();
        $flight = Flight::query()->sole();
        $this->assertSame(FlightPlanStatus::AwaitingPic, $flight->status);
        $this->assertSame('Manifest needed', $flight->documentSignatureStatus());
        $this->assertTrue($flight->isPicAuthorizationCurrent());
        $this->assertSame('SELF', $flight->pic_authorization_method);
        $package = $flight->filingAuthorizations()->sole();
        $this->assertSame($signature->id, $package->pilot_signature_id);
        $this->assertSame($signature->version, $package->signature_version);
        $this->assertSame($signature->sha256, $package->signature_sha256);
        $this->assertSame('password_session', $package->authentication['method']);
        $signed = base64_decode($package->pdf_documents['flight_plan']['bytes']);
        $unsigned = app(FilingDocumentRegistry::class)->render('flight_plan', $package->authorized_documents, null);
        $this->assertGreaterThan(substr_count($unsigned, '/Subtype /Image'), substr_count($signed, '/Subtype /Image'));
        $this->get(route('flights.view', $flight))->assertOk()->assertSee('Authorized PIC signature');
        $this->get(route('flights.pdf.download', $flight))->assertOk();
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame(0, $flight->events()->where('event_type', 'submitted_to_atc')->count());
    }

    public function test_manifest_submission_keeps_a_self_filed_flight_visible_until_explicit_reauthorization(): void
    {
        $pilot = $this->pilot();
        $signature = $this->signature($pilot);
        Livewire::actingAs($pilot)->test(CreateFlight::class)->fillForm($this->data())
            ->call('create')->assertActionMounted('authorizeFiling')->callMountedAction()
            ->assertHasNoErrors()->assertRedirect();
        $flight = Flight::query()->sole();
        $package = $flight->filingAuthorizations()->sole();
        $preserved = $package->pdf_documents;
        $this->assertTrue(Flight::query()->currentForPilot()->whereKey($flight->id)->exists());

        $response = $this->put(route('flights.manifest.update', $flight), [
            'aircraft_owner_name' => 'TEST OWNER', 'aircraft_owner_contact_number' => '1234567',
            'home_base_hangar' => 'RPUS', 'coa_expiry_date' => now()->addYear()->toDateString(),
            'total_cargo_weight' => '0.000',
            'persons' => [['full_name' => $pilot->fullName(), 'nationality' => 'PH', 'classification' => 'pic']],
        ])->assertSessionHasNoErrors();

        $flight->refresh();
        $this->assertSame(FlightPlanStatus::AwaitingPic, $flight->status);
        $this->assertFalse($flight->canSubmitToAtc());
        $this->assertTrue($flight->manifest()->exists());
        $this->assertTrue(Flight::query()->currentForPilot()->whereKey($flight->id)->exists());
        $this->assertFalse(Flight::query()->archivedForPilot()->whereKey($flight->id)->exists());
        $this->assertFalse(AwaitingAuthorizationFlightResource::getEloquentQuery()->whereKey($flight->id)->exists());
        $response->assertRedirectContains('confirm_signature=1');
        $this->get(route('flights.view', $flight))->assertOk()->assertSee('Authorized PIC signature');
        $this->assertNotNull(app(FilingAuthorizationService::class)->signatureBytes($flight));
        $this->assertSame(1, $flight->filingAuthorizations()->count());

        Livewire::actingAs($pilot)->withQueryParams(['confirm_signature' => 1])
            ->test(EditFlightManifest::class, ['flight' => $flight])->assertActionMounted('signManifest')
            ->callMountedAction()->assertHasNoErrors();

        $this->assertDatabaseCount('flights', 1);
        $this->assertSame(FlightPlanStatus::Pending, $flight->refresh()->status);
        $this->assertTrue($flight->isPicAuthorizationCurrent());
        $this->assertTrue(Flight::query()->currentForPilot()->whereKey($flight->id)->exists());
        $this->assertFalse(AwaitingAuthorizationFlightResource::getEloquentQuery()->whereKey($flight->id)->exists());
        $newPackage = $flight->filingAuthorizations()->latest('id')->firstOrFail();
        $this->assertSame($signature->id, $newPackage->pilot_signature_id);
        $this->assertCount(1, $newPackage->pdf_documents);
        $this->assertSame($preserved, $package->fresh()->pdf_documents);
        $this->get(route('filing.document', ['package' => $package, 'type' => 'flight_plan']))
            ->assertOk()->assertContent(base64_decode($preserved['flight_plan']['bytes']));
    }

    public function test_missing_and_pending_signatures_cannot_submit(): void
    {
        $pilot = $this->pilot();
        $page = Livewire::actingAs($pilot)->test(CreateFlight::class)->fillForm($this->data());
        $page->call('create')->assertHasErrors('data.signature')->assertFormSet(['route' => 'DCT']);
        PilotSignature::factory()->create(['user_id' => $pilot->id]);
        $page->call('create')->assertHasErrors('data.signature');
        $this->assertDatabaseCount('flights', 0);
    }

    public function test_signature_integrity_is_rechecked_at_confirmation_and_form_is_preserved(): void
    {
        $pilot = $this->pilot();
        $signature = $this->signature($pilot);
        $page = Livewire::actingAs($pilot)->test(CreateFlight::class)->fillForm($this->data())->call('create');
        Storage::disk('local')->put($signature->stored_path, 'tampered');
        $page->callMountedAction()->assertHasErrors('data.signature')->assertFormSet(['route' => 'DCT']);
        $this->assertDatabaseCount('flights', 0);
        $this->assertDatabaseCount('filing_authorizations', 0);
    }

    public function test_form_and_signature_changes_require_new_confirmation(): void
    {
        $pilot = $this->pilot();
        $this->signature($pilot);
        $page = Livewire::actingAs($pilot)->test(CreateFlight::class)->fillForm($this->data())->call('create');
        $page->set('data.route', 'DCT CHANGED')->callMountedAction()->assertHasErrors('data.signature');
        $this->assertDatabaseCount('flights', 0);
        $page->call('unmountAction')->call('create');
        $this->signature($pilot, 2);
        $page->callMountedAction()->assertHasErrors('data.signature');
        $this->assertDatabaseCount('flights', 0);
    }

    public function test_replayed_submission_does_not_create_duplicate_flights_or_authorizations(): void
    {
        $pilot = $this->pilot();
        $signature = $this->signature($pilot);
        $this->actingAs($pilot);
        $evidence = AuthenticationEvidence::fromSession(Auth::guard());
        $service = app(FlightPlanSubmissionService::class);
        $first = $service->submitSelfAuthorized($this->data(), $pilot, $evidence, $signature->id, 'test-submission');
        $second = $service->submitSelfAuthorized($this->data(), $pilot, $evidence, $signature->id, 'test-submission');
        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('flights', 1);
        $this->assertDatabaseCount('filing_authorizations', 1);
        $this->expectException(ValidationException::class);
        $service->submitSelfAuthorized([...$this->data(), 'route' => 'CHANGED'], $pilot, $evidence, $signature->id, 'test-submission');
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
        $fingerprint = $registry::documentFingerprint($registry->snapshots($flight), 'flight_plan');
        app(PicAuthorizationService::class)->authorizeSelfFiling($flight, $pilot,
            AuthenticationEvidence::fromSession(Auth::guard()), $signature->id, $fingerprint);
        $manifestFingerprint = FilingDocumentRegistry::documentFingerprint($registry->snapshots($flight), 'passenger_cargo_manifest');
        app(PicAuthorizationService::class)->authorizeSelfFiling($flight, $pilot,
            AuthenticationEvidence::fromSession(Auth::guard()), $signature->id, $manifestFingerprint, 'passenger_cargo_manifest');
        $package = $flight->filingAuthorizations()->latest('id')->firstOrFail();
        $this->assertCount(1, $package->pdf_documents);
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
            $this->assertSame(2, $flight->filingAuthorizations()->count());
        }
        $this->get(route('filing.document', ['package' => $package, 'type' => 'passenger_cargo_manifest']))
            ->assertOk()->assertContent(base64_decode($preserved['passenger_cargo_manifest']['bytes']));
    }

    public function test_pdf_render_failure_rolls_back_flight_package_and_events(): void
    {
        $pilot = $this->pilot();
        $signature = $this->signature($pilot);
        $this->actingAs($pilot);
        $this->mock(FlightPlanPdfService::class)->shouldReceive('renderSnapshot')->andThrow(new \RuntimeException('Synthetic PDF failure'));
        try {
            app(FlightPlanSubmissionService::class)->submitSelfAuthorized($this->data(), $pilot,
                AuthenticationEvidence::fromSession(Auth::guard()), $signature->id, 'failed-pdf');
            $this->fail('Expected renderer failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Synthetic PDF failure', $exception->getMessage());
            $this->assertDatabaseCount('flights', 0);
            $this->assertDatabaseCount('filing_authorizations', 0);
            $this->assertDatabaseCount((new FlightPlanEvent)->getTable(), 0);
        }
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
