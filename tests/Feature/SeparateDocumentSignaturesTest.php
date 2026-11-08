<?php

namespace Tests\Feature;

use App\Auth\AuthenticationEvidence;
use App\Domain\FlightPlans\Enums\FlightPlanStatus;
use App\Domain\FlightPlans\Services\AvsecVerificationService;
use App\Domain\FlightPlans\Services\FilingAuthorizationService;
use App\Domain\FlightPlans\Services\FlightPlanMutationService;
use App\Domain\FlightPlans\Services\FlightPlanQrPayloadService;
use App\Domain\FlightPlans\Services\PicAuthorizationService;
use App\Domain\FlightPlans\Support\FlightStatusDisplay;
use App\Domain\Pilots\Enums\PilotLicenseType;
use App\Domain\Users\Enums\UserRole;
use App\Filament\Panels\Atmo\Resources\AwaitingAvsecFlights\AwaitingAvsecFlightResource;
use App\Filament\Panels\Atmo\Resources\Flights\FlightResource as AtmoFlights;
use App\Filament\Panels\Pilot\Pages\EditFlightManifest;
use App\Filament\Panels\Pilot\Resources\MyCurrentFlights\MyCurrentFlightResource;
use App\Models\Flight;
use App\Models\Operator;
use App\Models\PilotSignature;
use App\Models\User;
use App\Services\ProfileUpdates\ProfileUpdateRequestService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SeparateDocumentSignaturesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        Filament::setCurrentPanel('pilot');
    }

    public function test_save_cancel_resume_signs_only_saved_manifest_and_preserves_flight_plan(): void
    {
        [$flight, $pilot] = $this->filing();
        $original = $flight->filingAuthorizations()->sole();
        $pdfs = $original->pdf_documents;
        $this->assertSame('Manifest needed', $flight->documentSignatureStatus());
        $this->saveManifest($flight, $pilot);
        $this->assertSame('Manifest signature needed', $flight->fresh()->documentSignatureStatus());
        $page = $this->manifestPage($flight, $pilot)->assertActionMounted('signManifest');
        $page->call('unmountAction');
        $this->assertNotNull($flight->manifest()->first());
        $this->assertNotNull(app(FilingAuthorizationService::class)->currentForDocument($flight, 'flight_plan'));
        $this->assertFalse($flight->canSubmitToAtc());
        $this->assertTrue(MyCurrentFlightResource::getEloquentQuery()->whereKey($flight)->exists());
        $page->mountAction('signManifest')->callMountedAction()->assertHasNoErrors();
        $this->assertTrue($flight->fresh()->canSubmitToAtc());
        $this->assertSame(FlightPlanStatus::Pending, $flight->fresh()->status);
        $this->assertSame($pdfs, $original->fresh()->pdf_documents);
        $this->assertSame(['flight_plan'], $original->signed_document_types);
        $manifestPackage = $flight->filingAuthorizations()->latest('id')->firstOrFail();
        $this->assertSame(['passenger_cargo_manifest'], $manifestPackage->signed_document_types);
        $this->assertSame(['passenger_cargo_manifest'], array_keys($manifestPackage->pdf_documents));
    }

    public function test_manifest_edit_invalidates_only_manifest_and_keeps_flight_in_current(): void
    {
        [$flight, $pilot] = $this->filing();
        $this->saveManifest($flight, $pilot);
        $this->manifestPage($flight, $pilot)->callMountedAction()->assertHasNoErrors();
        $flightEvidence = app(FilingAuthorizationService::class)->currentForDocument($flight, 'flight_plan');
        $manifestEvidence = app(FilingAuthorizationService::class)->currentForDocument($flight, 'passenger_cargo_manifest');
        $preserved = $manifestEvidence->pdf_documents;
        $this->saveManifest($flight, $pilot, ['remarks' => 'Changed cargo instructions']);
        $this->assertSame($flightEvidence->id, app(FilingAuthorizationService::class)->currentForDocument($flight, 'flight_plan')->id);
        $this->assertNull(app(FilingAuthorizationService::class)->currentForDocument($flight, 'passenger_cargo_manifest'));
        $this->assertSame('Manifest signature needed', $flight->fresh()->documentSignatureStatus());
        $this->assertTrue(Flight::query()->currentForPilot()->whereKey($flight)->exists());
        $this->assertSame($preserved, $manifestEvidence->fresh()->pdf_documents);
        $this->get(route('filing.document', ['package' => $manifestEvidence, 'type' => 'passenger_cargo_manifest']))
            ->assertOk()->assertContent(base64_decode($preserved['passenger_cargo_manifest']['bytes']));
    }

    public function test_flight_plan_edit_and_revert_requires_new_signature_but_unchanged_manifest_stays_signed(): void
    {
        [$flight, $pilot] = $this->filing();
        $this->saveManifest($flight, $pilot);
        $this->manifestPage($flight, $pilot)->callMountedAction()->assertHasNoErrors();
        $manifest = app(FilingAuthorizationService::class)->currentForDocument($flight, 'passenger_cargo_manifest');
        $oldRoute = $flight->fresh()->route;
        $flight->fresh()->update(['route' => 'CHANGED']);
        $flight->fresh()->update(['route' => $oldRoute]);
        $this->assertNull(app(FilingAuthorizationService::class)->currentForDocument($flight, 'flight_plan'));
        $this->assertSame($manifest->id, app(FilingAuthorizationService::class)->currentForDocument($flight, 'passenger_cargo_manifest')->id);
        $this->assertFalse($flight->canSubmitToAtc());
        Livewire::actingAs($pilot)->withQueryParams(['confirm_flight_plan' => 1])->test(EditFlightManifest::class, ['flight' => $flight->fresh()])
            ->assertActionMounted('signFlightPlan')->callMountedAction()->assertHasNoErrors();
        $this->assertTrue($flight->fresh()->canSubmitToAtc());
    }

    public function test_stale_manifest_confirmation_preserves_latest_saved_version(): void
    {
        [$flight, $pilot] = $this->filing();
        $this->saveManifest($flight, $pilot);
        $page = $this->manifestPage($flight, $pilot);
        $this->saveManifest($flight, $pilot, ['remarks' => 'New saved version']);
        $page->callMountedAction()->assertHasErrors('signature');
        $this->assertSame('New saved version', $flight->manifest()->first()->remarks);
        $this->assertFalse($flight->canSubmitToAtc());
        $this->assertDatabaseCount('filing_authorizations', 1);
    }

    public function test_failed_signature_and_replacement_do_not_discard_saved_manifest(): void
    {
        [$flight, $pilot] = $this->filing();
        $this->saveManifest($flight, $pilot);
        $replacement = $this->signature($pilot, 2);
        $page = $this->manifestPage($flight, $pilot);
        Storage::disk('local')->put($replacement->stored_path, 'tampered');
        $page->callMountedAction()->assertHasErrors('signature');
        $this->assertNotNull($flight->manifest()->first());
        $this->assertSame('Manifest signature needed', $flight->fresh()->documentSignatureStatus());
        $this->assertNotNull(app(FilingAuthorizationService::class)->currentForDocument($flight, 'flight_plan'));
        $page->call('unmountAction')->mountAction('signManifest');
        $this->signature($pilot, 3);
        $page->callMountedAction()->assertHasErrors('signature');
        $this->assertFalse($flight->canSubmitToAtc());
    }

    public function test_atmo_pending_and_all_direct_access_are_gated_until_both_documents_are_signed(): void
    {
        [$flight, $pilot] = $this->filing();
        $atmo = User::factory()->create(['role' => UserRole::Atmo, 'station' => 'RPUS']);
        foreach ([false, true] as $saved) {
            if ($saved) {
                $this->saveManifest($flight, $pilot);
            }
            $this->assertHiddenFromAtmo($flight, $atmo);
        }
        $this->manifestPage($flight, $pilot)->callMountedAction()->assertHasNoErrors();
        $this->actingAs($atmo);
        Filament::setCurrentPanel('atmo');
        $this->assertFalse(AtmoFlights::getEloquentQuery()->whereKey($flight)->exists());
        $this->assertTrue(AwaitingAvsecFlightResource::getEloquentQuery()->whereKey($flight)->exists());
        foreach (['flights.view', 'flights.pdf.download', 'flights.qr', 'flights.qr.download', 'flights.manifest.pdf'] as $route) {
            $this->get(route($route, $flight))->assertOk();
        }
        $this->assertFalse($atmo->can('accept', $flight->fresh()));
        app(AvsecVerificationService::class)->confirm(
            app(FlightPlanQrPayloadService::class)->buildPayload($flight->fresh()),
            User::factory()->create(['role' => UserRole::Avsec, 'station' => 'RPUS']), true,
        );
        $this->assertTrue(AtmoFlights::getEloquentQuery()->whereKey($flight)->exists());
        $this->assertTrue($atmo->can('accept', $flight->fresh()));
        app(FlightPlanMutationService::class)->accept($flight, $atmo, 'TEST');
        $this->assertTrue($flight->fresh()->hasBeenAccepted());
    }

    public function test_representative_qr_authorizes_current_documents_and_later_manifest_edit_hides_flight(): void
    {
        [$flight, $pilot] = $this->filing(false);
        $dispatch = User::factory()->create(['role' => UserRole::Dispatch, 'operator_id' => $pilot->operator_id]);
        $flight->update(['prepared_by_user_id' => $dispatch->id, 'filed_by_user_id' => $dispatch->id]);
        $this->saveManifest($flight, $dispatch);
        $this->actingAs($pilot);
        $service = app(PicAuthorizationService::class);
        $payload = app(FlightPlanQrPayloadService::class)->buildPayload($flight->fresh());
        $token = $service->createAuthorizationHandoff($service->resolveAccessibleFlightFromPayload($payload, $pilot));
        $service->authorizeFromHandoff($token, $pilot, AuthenticationEvidence::fromSession(Auth::guard()), true);
        $this->assertTrue($flight->fresh()->canSubmitToAtc());
        $this->assertSame('QR', $flight->fresh()->pic_authorization_method);
        $this->saveManifest($flight, $dispatch, ['remarks' => 'Representative change']);
        $this->assertNotNull(app(FilingAuthorizationService::class)->currentForDocument($flight, 'flight_plan'));
        $this->assertFalse($flight->canSubmitToAtc());
        $this->manifestPage($flight, $pilot)->callMountedAction()->assertHasNoErrors();
        $this->assertTrue($flight->fresh()->canSubmitToAtc());
    }

    private function assertHiddenFromAtmo(Flight $flight, User $atmo): void
    {
        $this->actingAs($atmo);
        Filament::setCurrentPanel('atmo');
        $this->assertFalse(AtmoFlights::getEloquentQuery()->whereKey($flight)->exists());
        $this->assertFalse($atmo->can('accept', $flight->fresh()));
        foreach (['flights.view', 'flights.pdf.download', 'flights.qr', 'flights.qr.download', 'flights.manifest.pdf'] as $route) {
            $this->get(route($route, $flight))->assertStatus($route === 'flights.manifest.pdf' && ! $flight->manifest()->exists() ? 404 : 403);
        }
        $this->post(route('flights.accept', $flight))->assertForbidden();
        $payload = app(FlightPlanQrPayloadService::class)->buildPayload($flight->fresh());
        $this->post(route('flightplan.scan-qr.lookup'), ['payload' => $payload])->assertForbidden();
        $this->get(route('filing.history', $flight))->assertForbidden();
        $package = $flight->filingAuthorizations()->firstOrFail();
        $this->get(route('filing.document', ['package' => $package, 'type' => 'flight_plan']))->assertForbidden();
    }

    public function test_latest_signature_integrity_failure_does_not_restore_an_older_signature(): void
    {
        [$flight, $pilot] = $this->filing();
        $this->saveManifest($flight, $pilot);
        $this->manifestPage($flight, $pilot)->callMountedAction()->assertHasNoErrors();
        $replacement = $this->signature($pilot, 2);
        app(PicAuthorizationService::class)->authorizeSelfFiling($flight, $pilot,
            AuthenticationEvidence::fromSession(Auth::guard()), $replacement->id);
        $this->assertTrue($flight->fresh()->canSubmitToAtc());
        Storage::disk('local')->put($replacement->stored_path, 'tampered');
        $this->assertNull(app(FilingAuthorizationService::class)->currentForDocument($flight, 'flight_plan'));
        $this->assertFalse($flight->fresh()->canSubmitToAtc());
        $this->assertNotNull(app(FilingAuthorizationService::class)->currentForDocument($flight, 'passenger_cargo_manifest'));
    }

    public function test_expired_pilot_badge_keeps_expiration_precedence(): void
    {
        $flight = new Flight(['status' => FlightPlanStatus::Pending, 'date_of_flight' => now('Asia/Manila')->subDays(2)->toDateString()]);
        $this->assertSame(FlightStatusDisplay::EXPIRED, FlightStatusDisplay::badge($flight, UserRole::Pilot)['key']);
    }

    public function test_manifest_revision_does_not_make_a_flight_plan_confirmation_stale(): void
    {
        [$flight, $pilot] = $this->filing();
        $this->saveManifest($flight, $pilot);
        $flight->update(['route' => 'NEW ROUTE']);
        $page = Livewire::actingAs($pilot)->withQueryParams(['confirm_flight_plan' => 1])
            ->test(EditFlightManifest::class, ['flight' => $flight->fresh()]);
        $this->saveManifest($flight, $pilot, ['remarks' => 'Separate manifest revision']);
        $page->callMountedAction()->assertHasNoErrors();
        $this->assertNotNull(app(FilingAuthorizationService::class)->currentForDocument($flight, 'flight_plan'));
        $this->assertSame('Manifest signature needed', $flight->fresh()->documentSignatureStatus());
    }

    public function test_new_unsigned_document_decision_does_not_restore_an_older_signature(): void
    {
        [$flight, $pilot] = $this->filing();
        $this->saveManifest($flight, $pilot);
        $this->manifestPage($flight, $pilot)->callMountedAction()->assertHasNoErrors();
        $packages = app(FilingAuthorizationService::class);
        $package = $packages->request($flight, ['flight_plan']);
        $packages->decide($package, $flight, $pilot, true, null, null);
        $this->assertNull($packages->currentForDocument($flight, 'flight_plan'));
        $this->assertNotNull($packages->currentForDocument($flight, 'passenger_cargo_manifest'));
        $this->assertFalse($flight->fresh()->canSubmitToAtc());
    }

    public function test_no_op_manifest_save_preserves_signatures_but_shared_rendered_fields_require_both_again(): void
    {
        [$flight, $pilot] = $this->filing();
        $this->saveManifest($flight, $pilot);
        $this->manifestPage($flight, $pilot)->callMountedAction()->assertHasNoErrors();
        $revision = $flight->fresh()->revision_number;
        $this->saveManifest($flight, $pilot);
        $this->assertTrue($flight->fresh()->canSubmitToAtc());
        $this->assertSame($revision, $flight->fresh()->revision_number);
        $flight->update(['aircraft_identification' => 'RPCNEW']);
        $packages = app(FilingAuthorizationService::class);
        $this->assertNull($packages->currentForDocument($flight, 'flight_plan'));
        $this->assertNull($packages->currentForDocument($flight, 'passenger_cargo_manifest'));
        $this->assertFalse($flight->fresh()->canSubmitToAtc());
    }

    public function test_legacy_signed_packages_remain_current_without_rewriting_historical_evidence(): void
    {
        [$flight, $pilot] = $this->filing(false);
        $dispatch = User::factory()->create(['role' => UserRole::Dispatch, 'operator_id' => $pilot->operator_id]);
        $flight->update(['prepared_by_user_id' => $dispatch->id, 'filed_by_user_id' => $dispatch->id]);
        $this->saveManifest($flight, $pilot);
        $service = app(PicAuthorizationService::class);
        $token = $service->createAuthorizationHandoff($flight);
        $service->authorizeFromHandoff($token, $pilot, AuthenticationEvidence::fromSession(Auth::guard()), true);
        $original = $flight->filingAuthorizations()->sole();
        $legacy = $original->replicate();
        $documents = $original->authorized_documents;
        foreach ($documents as &$document) {
            unset($document['document_revision']);
        }
        unset($document);
        $legacy->forceFill(['signed_document_types' => null, 'documents' => $documents,
            'authorized_documents' => $documents])->save();
        $evidence = $legacy->getRawOriginal();
        $packages = app(FilingAuthorizationService::class);
        $this->assertSame($legacy->id, $packages->currentForDocument($flight, 'flight_plan')->id);
        $this->assertSame($legacy->id, $packages->currentForDocument($flight, 'passenger_cargo_manifest')->id);
        $this->assertTrue($flight->fresh()->canSubmitToAtc());
        $this->assertEquals($evidence, $legacy->fresh()->getRawOriginal());
        $this->assertSame($original->pdf_documents, $legacy->pdf_documents);
    }

    private function filing(bool $sign = true): array
    {
        $pilot = User::factory()->create(['role' => UserRole::Pilot, 'operator_id' => Operator::factory()->create()->id]);
        $pilot->pilotProfile()->create(['license_type' => PilotLicenseType::CommercialPilot, 'license_number' => 'TEST-123',
            'ratings' => 'C172', 'license_expiry_date' => now()->addYears(2)->toDateString()]);
        $signature = $this->signature($pilot);
        $flight = Flight::factory()->create(['status' => FlightPlanStatus::AwaitingPic,
            'operator_id' => $pilot->operator_id, 'prepared_by_user_id' => $pilot->id, 'filed_by_user_id' => $pilot->id,
            'pilot_in_command_user_id' => $pilot->id, 'pilot_id' => $pilot->id,
            'pilot_in_command' => strtoupper($pilot->fullName()), 'persons_on_board' => 1])->refresh();
        $this->actingAs($pilot);
        if ($sign) {
            app(PicAuthorizationService::class)->authorizeSelfFiling($flight, $pilot,
                AuthenticationEvidence::fromSession(Auth::guard()), $signature->id);
        }

        return [$flight->fresh(), $pilot];
    }

    private function saveManifest(Flight $flight, User $actor, array $extra = []): void
    {
        $this->actingAs($actor);
        $this->put(route('flights.manifest.update', $flight), [...[
            'aircraft_owner_name' => 'TEST OWNER', 'aircraft_owner_contact_number' => '1234567',
            'home_base_hangar' => 'RPUS', 'coa_expiry_date' => now()->addYear()->toDateString(), 'total_cargo_weight' => '0.000',
            'persons' => [['full_name' => $flight->fresh()->pilot_in_command, 'nationality' => 'PH', 'classification' => 'pic']],
        ], ...$extra])->assertSessionHasNoErrors()->assertRedirect();
    }

    private function manifestPage(Flight $flight, User $pilot)
    {
        Filament::setCurrentPanel('pilot');

        return Livewire::actingAs($pilot)->withQueryParams(['confirm_signature' => 1])
            ->test(EditFlightManifest::class, ['flight' => $flight->fresh()]);
    }

    private function signature(User $pilot, int $version = 1): PilotSignature
    {
        $signature = PilotSignature::factory()->create(['user_id' => $pilot->id, 'version' => $version]);
        app(ProfileUpdateRequestService::class)->approve($signature->request, User::factory()->create(['role' => UserRole::Admin]));

        return $signature->fresh();
    }
}
