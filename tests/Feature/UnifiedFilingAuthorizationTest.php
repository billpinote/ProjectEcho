<?php

namespace Tests\Feature;

use App\Auth\AuthenticationEvidence;
use App\Domain\FlightPlans\Enums\FlightPlanStatus;
use App\Domain\FlightPlans\Services\FilingAuthorizationService;
use App\Domain\FlightPlans\Services\FlightManifestService;
use App\Domain\FlightPlans\Services\FlightPlanMutationService;
use App\Domain\FlightPlans\Services\ManifestPdfService;
use App\Domain\FlightPlans\Services\PicAuthorizationService;
use App\Domain\FlightPlans\Support\FilingDocumentRegistry;
use App\Domain\Pilots\Enums\PilotLicenseType;
use App\Domain\Users\Enums\UserRole;
use App\Filament\Panels\Pilot\Resources\Flights\Pages\CreateFlight;
use App\Models\FilingAuthorization;
use App\Models\Flight;
use App\Models\FlightManifest;
use App\Models\Operator;
use App\Models\PilotSignature;
use App\Models\User;
use App\Services\ProfileUpdates\ProfileUpdateRequestService;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class UnifiedFilingAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
    }

    #[DataProvider('documentSets')]
    public function test_one_signed_decision_preserves_every_included_document(string $kind): void
    {
        [$flight, $pilot, $preparer] = $this->filing();
        if ($kind !== 'flight') {
            $this->manifest($flight, $pilot, $preparer, $kind === 'cargo');
        }
        $signature = $this->signature($pilot);
        $this->authorize($flight, $pilot, true);
        $package = $flight->filingAuthorizations()->sole();

        $this->assertSame('authorized', $package->status);
        $this->assertSame($pilot->id, $package->pic_user_id);
        $this->assertSame($signature->id, $package->pilot_signature_id);
        $this->assertSame($signature->version, $package->signature_version);
        $this->assertSame($signature->sha256, $package->signature_sha256);
        $this->assertSame('password_session', $package->authentication['method']);
        $this->assertFalse($package->authentication['freshly_verified']);
        $this->assertCount($kind === 'flight' ? 1 : 2, $package->documents);
        $this->assertCount(count($package->documents), $package->pdf_documents);
        $this->assertTrue($flight->refresh()->isPicAuthorizationCurrent());
        $this->assertNotNull(app(FilingAuthorizationService::class)->signatureBytes($flight));
        $this->assertSame(FilingDocumentRegistry::fingerprint($package->authorized_documents), $package->authorized_fingerprint);
        $this->assertEmpty(Storage::disk('public')->allFiles());
        foreach ($package->pdf_documents as $type => $pdf) {
            $bytes = base64_decode($pdf['bytes']);
            $this->assertStringStartsWith('%PDF-', $bytes);
            $this->assertSame(hash('sha256', $bytes), $pdf['sha256']);
            $this->get(route('filing.document', ['package' => $package, 'type' => $type]))
                ->assertOk()->assertContent($bytes);
        }
        $this->get(route('flights.view', $flight))->assertOk()->assertSee('Authorized PIC signature');
    }

    public static function documentSets(): array
    {
        return ['flight only' => ['flight'], 'passengers' => ['passengers'], 'cargo' => ['cargo']];
    }

    public function test_unsigned_authorization_remains_available_without_an_approved_signature(): void
    {
        [$flight, $pilot] = $this->filing();
        $this->authorize($flight, $pilot, false);
        $this->assertTrue($flight->refresh()->isPicAuthorizationCurrent());
        $this->assertNull($flight->filingAuthorizations()->sole()->pilot_signature_id);
        $this->assertNull(app(FilingAuthorizationService::class)->signatureBytes($flight));
    }

    public function test_pending_signature_cannot_be_applied_and_handoff_is_not_consumed(): void
    {
        [$flight, $pilot] = $this->filing();
        PilotSignature::factory()->create(['user_id' => $pilot->id]);
        $service = app(PicAuthorizationService::class);
        $token = $service->createAuthorizationHandoff($flight);
        try {
            $service->authorizeFromHandoff($token, $pilot, applySignature: true);
            $this->fail('A pending signature was applied.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('signature', $exception->errors());
            $this->assertNotNull($service->resolveAuthorizationHandoff($token));
            $this->assertNull($flight->refresh()->pic_authorized_at);
            $this->assertSame('pending', $flight->filingAuthorizations()->sole()->status);
        }
    }

    public function test_tampered_signature_fails_safely(): void
    {
        [$flight, $pilot] = $this->filing();
        $signature = $this->signature($pilot);
        Storage::disk('local')->put($signature->stored_path, 'tampered');
        $service = app(PicAuthorizationService::class);
        $token = $service->createAuthorizationHandoff($flight);
        try {
            $service->authorizeFromHandoff($token, $pilot, applySignature: true);
            $this->fail('Tampered bytes were accepted.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
            $this->assertNull($flight->refresh()->pic_authorized_at);
            $this->assertNotNull($service->resolveAuthorizationHandoff($token));
        }
    }

    public function test_signature_replacement_cannot_change_historical_documents(): void
    {
        [$flight, $pilot] = $this->filing();
        $first = $this->signature($pilot);
        $this->authorize($flight, $pilot, true);
        $package = $flight->filingAuthorizations()->sole();
        $pdfs = $package->pdf_documents;
        $replacement = $this->signature($pilot, 2);

        $this->assertSame('superseded', $first->fresh()->status);
        $this->assertSame($replacement->id, $pilot->approvedSignature()->first()->id);
        $this->assertSame($pdfs, $package->fresh()->pdf_documents);
        $this->assertSame($first->id, $package->fresh()->pilot_signature_id);
        $this->assertSame(Storage::disk('local')->get($first->stored_path), app(FilingAuthorizationService::class)->signatureBytes($flight->refresh()));
    }

    public function test_material_flight_edit_invalidates_current_signature_but_preserves_history(): void
    {
        [$flight, $pilot] = $this->filing();
        $this->signature($pilot);
        $this->authorize($flight, $pilot, true);
        $package = $flight->filingAuthorizations()->sole();
        $original = $package->pdf_documents;
        $revision = $flight->refresh()->revision_number;
        $flight->update(['route' => 'DCT NEWROUTE']);

        $this->assertSame($revision + 1, $flight->refresh()->revision_number);
        $this->assertFalse($flight->isPicAuthorizationCurrent());
        $this->assertFalse($flight->canSubmitToAtc());
        $this->assertNull(app(FilingAuthorizationService::class)->signatureBytes($flight));
        $this->assertSame($original, $package->fresh()->pdf_documents);
        $this->get(route('filing.history', $flight))->assertOk()->assertSee('Package #'.$package->id);
        $this->get(route('filing.document', ['package' => $package, 'type' => 'flight_plan']))
            ->assertOk()->assertContent(base64_decode($original['flight_plan']['bytes']));
    }

    public function test_manifest_and_person_changes_require_renewed_authorization(): void
    {
        [$flight, $pilot, $preparer] = $this->filing();
        $manifest = $this->manifest($flight, $pilot, $preparer, true);
        $this->signature($pilot);
        $this->authorize($flight, $pilot, true);
        $manifest->update(['cargo_quantity' => '3 boxes']);
        $this->assertTrue($flight->refresh()->isPicAuthorizationCurrent());
        $this->assertFalse($flight->canSubmitToAtc());
        $this->authorize($flight, $pilot, true);
        $manifest->persons()->first()->update(['nationality' => 'US']);
        $this->assertTrue($flight->refresh()->isPicAuthorizationCurrent());
        $this->assertFalse($flight->canSubmitToAtc());
        $this->assertCount(2, $flight->filingAuthorizations()->get());
    }

    public function test_a_manifest_added_after_review_is_not_silently_authorized(): void
    {
        [$flight, $pilot, $preparer] = $this->filing();
        $service = app(PicAuthorizationService::class);
        $token = $service->createAuthorizationHandoff($flight);
        $this->manifest($flight, $pilot, $preparer, false);
        $this->expectException(ValidationException::class);
        $service->authorizeFromHandoff($token, $pilot);
    }

    public function test_bulk_manifest_change_is_detected_even_without_model_events(): void
    {
        [$flight, $pilot, $preparer] = $this->filing();
        $manifest = $this->manifest($flight, $pilot, $preparer, false);
        $this->authorize($flight, $pilot, false);
        $manifest->newQuery()->whereKey($manifest->id)->update(['remarks' => 'Changed externally']);
        $this->assertTrue($flight->refresh()->isPicAuthorizationCurrent());
        $this->assertFalse($flight->canSubmitToAtc());
    }

    public function test_routine_operational_updates_preserve_authorization(): void
    {
        [$flight, $pilot] = $this->filing();
        $this->signature($pilot);
        $this->authorize($flight, $pilot, true);
        $revision = $flight->refresh()->revision_number;
        $flight->update(['revised_eobt' => '15:00', 'reviewed_at' => now(), 'received_by' => 'ATMO', 'time_start_up' => '14:45']);
        $this->assertTrue($flight->refresh()->isPicAuthorizationCurrent());
        $this->assertSame($revision, $flight->revision_number);
        $this->assertNotNull(app(FilingAuthorizationService::class)->signatureBytes($flight));
    }

    public function test_decline_preserves_unsigned_reviewed_package(): void
    {
        [$flight, $pilot] = $this->filing();
        $this->signature($pilot);
        $service = app(PicAuthorizationService::class);
        $service->declineFromHandoff($service->createAuthorizationHandoff($flight), $pilot, 'Correct route.');
        $package = $flight->filingAuthorizations()->sole();
        $this->assertSame('declined', $package->status);
        $this->assertNull($package->pilot_signature_id);
        $this->assertNull(app(FilingAuthorizationService::class)->signatureBytes($flight->refresh()));
    }

    public function test_unrelated_user_cannot_read_package_or_private_pdf(): void
    {
        [$flight, $pilot] = $this->filing();
        $this->authorize($flight, $pilot, false);
        $package = $flight->filingAuthorizations()->sole();
        $unrelated = User::factory()->create(['role' => UserRole::Pilot]);
        $this->actingAs($unrelated)->get(route('filing.document', ['package' => $package, 'type' => 'flight_plan']))->assertForbidden();
        $this->get(route('flights.pdf.download', $flight))->assertForbidden();
    }

    public function test_self_filing_signature_requires_a_separate_explicit_action(): void
    {
        [$flight, $pilot] = $this->filing();
        $flight->update(['prepared_by_user_id' => $pilot->id, 'pilot_in_command_user_id' => $pilot->id]);
        $this->signature($pilot);
        $this->actingAs($pilot);
        $this->assertNull(app(FilingAuthorizationService::class)->signatureBytes($flight));
        $this->get(route('filing.review-self', $flight))->assertRedirect();
        $this->get(route('flights.view', $flight))->assertOk()->assertSee('Review and authorize this filing');
        $service = app(PicAuthorizationService::class);
        $token = $service->createAuthorizationHandoff($flight->refresh(), selfPrepared: true);
        $this->post(route('filing.authorize-self', ['token' => $token]), ['apply_signature' => 1])->assertRedirect(route('flights.view', $flight));
        $this->assertNotNull(app(FilingAuthorizationService::class)->signatureBytes($flight->refresh()));
    }

    public function test_canonical_fingerprints_ignore_key_order_but_preserve_person_order(): void
    {
        $this->assertSame(FilingDocumentRegistry::fingerprint(['b' => ['z' => 2, 'a' => 1], 'a' => 0]), FilingDocumentRegistry::fingerprint(['a' => 0, 'b' => ['a' => 1, 'z' => 2]]));
        $this->assertNotSame(FilingDocumentRegistry::fingerprint(['persons' => ['A', 'B']]), FilingDocumentRegistry::fingerprint(['persons' => ['B', 'A']]));
    }

    public function test_manifest_no_op_save_does_not_invalidate_a_decision(): void
    {
        [$flight, $pilot, $preparer] = $this->filing();
        $manifest = $this->manifest($flight, $pilot, $preparer, false);
        $this->authorize($flight, $pilot, false);
        $revision = $flight->refresh()->revision_number;
        $this->manifest($flight, $pilot, $preparer, false);
        $this->assertTrue($flight->refresh()->isPicAuthorizationCurrent());
        $this->assertSame($revision, $flight->revision_number);
    }

    public function test_failed_document_render_rolls_back_the_entire_decision(): void
    {
        [$flight, $pilot, $preparer] = $this->filing();
        $this->manifest($flight, $pilot, $preparer, false);
        $service = app(PicAuthorizationService::class);
        $token = $service->createAuthorizationHandoff($flight);
        $this->mock(ManifestPdfService::class)
            ->shouldReceive('render')->once()->andThrow(new \RuntimeException('Render unavailable.'));
        try {
            $service->authorizeFromHandoff($token, $pilot);
            $this->fail('The decision was committed without its documents.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Render unavailable.', $exception->getMessage());
            $this->assertNull($flight->refresh()->pic_authorized_at);
            $this->assertSame('pending', $flight->filingAuthorizations()->sole()->status);
            $this->assertNotNull($service->resolveAuthorizationHandoff($token));
        }
    }

    public function test_cross_operator_pilot_cannot_sign_a_package(): void
    {
        [$flight] = $this->filing();
        $other = User::factory()->create(['role' => UserRole::Pilot, 'operator_id' => Operator::factory()->create()->id]);
        $this->expectException(ValidationException::class);
        app(PicAuthorizationService::class)->authorizeFromHandoff(
            app(PicAuthorizationService::class)->createAuthorizationHandoff($flight), $other, applySignature: true,
        );
    }

    public function test_decided_package_cannot_be_rewritten_or_deleted(): void
    {
        [$flight, $pilot] = $this->filing();
        $this->authorize($flight, $pilot, false);
        $package = $flight->filingAuthorizations()->sole();
        try {
            $package->forceFill(['reason' => 'Rewrite history'])->save();
            $this->fail('Decided evidence was modified.');
        } catch (ValidationException) {
            $this->assertNull($package->fresh()->reason);
        }
        $this->expectException(QueryException::class);
        FilingAuthorization::query()->whereKey($package->id)->delete();
    }

    public function test_correct_and_resubmit_creates_a_new_unsigned_filing(): void
    {
        [$flight, $pilot, $preparer] = $this->filing();
        $preparer->update(['role' => UserRole::Pilot]);
        $service = app(PicAuthorizationService::class);
        $service->declineFromHandoff($service->createAuthorizationHandoff($flight), $pilot, 'Correct route.');
        Filament::setCurrentPanel(Filament::getPanel('pilot'));
        Livewire::actingAs($preparer)->withQueryParams(['correct_from' => $flight->id])
            ->test(CreateFlight::class)
            ->fillForm([
                'filing_capacity' => 'for_another_pic', 'date_of_flight' => $flight->date_of_flight,
                'aircraft_identification' => 'RPC123', 'flight_rules' => 'I', 'type_of_flight' => 'S',
                'number' => '1', 'type_of_aircraft' => 'C172', 'wake_turbulence_cat' => 'L',
                'equipment_10a' => 'S', 'equipment_10b' => 'C', 'departure_aerodrome' => 'RPUS',
                'destination_aerodrome' => 'RPLL', 'proposed_time' => '1430', 'cruising_speed' => 'N0100',
                'level' => 'F100', 'route' => 'DCT', 'total_eet' => '0100', 'endurance' => '0300',
                'persons_on_board' => '1', 'other_information' => 'DOF/'.str_replace('-', '', $flight->date_of_flight),
                'dinghies_enabled' => false, 'authorized_representative_enabled' => true,
            ])->call('create')->assertHasNoFormErrors();
        $corrected = Flight::query()->where('revision_of_id', $flight->id)->sole();
        $this->assertTrue($corrected->requiresPicAuthorization());
        $this->assertFalse($corrected->isPicAuthorizationCurrent());
        $this->assertSame(0, $corrected->filingAuthorizations()->count());
        $this->assertSame('declined', $flight->filingAuthorizations()->sole()->status);
    }

    public function test_signed_manifest_acceptance_retains_signature_and_operational_updates(): void
    {
        [$flight, $pilot, $preparer] = $this->filing();
        $manifest = $this->manifest($flight, $pilot, $preparer, true);
        $this->signature($pilot);
        $this->authorize($flight, $pilot, true);
        $atmo = User::factory()->create(['role' => UserRole::Atmo, 'station' => 'RPUS']);
        app(FlightPlanMutationService::class)->accept($flight, $atmo, 'TEST');
        $flight->refresh();
        $manifest->refresh();
        $pdfs = app(ManifestPdfService::class);
        $original = $pdfs->forFlight($flight, $manifest);
        $this->assertStringContainsString('/Subtype /Image', $original);
        $this->assertTrue($flight->isPicAuthorizationCurrent());
        $flight->update(['time_airborne' => '14:50']);
        $this->assertTrue($flight->refresh()->isPicAuthorizationCurrent());
        $this->assertSame($original, $pdfs->forFlight($flight, $manifest->fresh()));
    }

    public function test_guest_qr_preview_cannot_expose_the_private_signature_image(): void
    {
        [$flight, $pilot] = $this->filing();
        $this->signature($pilot);
        $this->authorize($flight, $pilot, true);
        Auth::logout();
        $this->withSession(['scanned_flight_plan_previews' => ['test-token' => [
            'flight_id' => $flight->id, 'snapshot' => $flight->toArray(),
        ]]])->get(route('flightplan.scan-qr.preview', ['token' => 'test-token']))
            ->assertOk()->assertDontSee('Authorized PIC signature');
    }

    public function test_signature_is_embedded_in_each_pdf_renderer(): void
    {
        [$flight, $pilot, $preparer] = $this->filing();
        $this->manifest($flight, $pilot, $preparer, true);
        $this->signature($pilot);
        $this->authorize($flight, $pilot, true);
        $package = $flight->filingAuthorizations()->sole();
        foreach ($package->pdf_documents as $type => $pdf) {
            $unsigned = app(FilingDocumentRegistry::class)->render($type, $package->authorized_documents, null);
            $this->assertGreaterThan(substr_count($unsigned, '/Subtype /Image'), substr_count(base64_decode($pdf['bytes']), '/Subtype /Image'));
        }
    }

    public function test_registry_cannot_add_an_unreviewed_document_during_pic_assignment(): void
    {
        [$flight, $pilot] = $this->filing();
        app(FilingDocumentRegistry::class)->register('test_attachment',
            fn (Flight $candidate): ?array => $candidate->pilot_in_command_user_id === null ? null : [
                'id' => 123, 'revision' => 1, 'content' => ['notes' => 'Not presented for review'],
            ],
            function (): string {
                throw new \LogicException('An unreviewed renderer must not run.');
            },
        );
        $service = app(PicAuthorizationService::class);
        $token = $service->createAuthorizationHandoff($flight);
        try {
            $service->authorizeFromHandoff($token, $pilot);
            $this->fail('A document absent from the reviewed package was authorized.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payload', $exception->errors());
            $this->assertNull($flight->refresh()->pic_authorized_at);
            $this->assertNotNull($service->resolveAuthorizationHandoff($token));
        }
    }

    private function filing(): array
    {
        $operator = Operator::factory()->create();
        $pilot = User::factory()->create(['role' => UserRole::Pilot, 'operator_id' => $operator->id]);
        $pilot->pilotProfile()->create([
            'license_type' => PilotLicenseType::CommercialPilot, 'license_number' => 'TEST-123',
            'ratings' => 'C172', 'license_expiry_date' => now()->addYear()->toDateString(),
        ]);
        $preparer = User::factory()->create(['role' => UserRole::Dispatch, 'operator_id' => $operator->id]);
        $flight = Flight::factory()->create([
            'status' => FlightPlanStatus::AwaitingPic, 'operator_id' => $operator->id,
            'prepared_by_user_id' => $preparer->id, 'filed_by_user_id' => $preparer->id,
            'pilot_in_command_user_id' => null, 'pilot_in_command' => null,
            'date_of_flight' => now()->addDay()->toDateString(), 'persons_on_board' => 1,
        ])->refresh();

        return [$flight, $pilot, $preparer];
    }

    private function signature(User $pilot, int $version = 1): PilotSignature
    {
        $signature = PilotSignature::factory()->create(['user_id' => $pilot->id, 'version' => $version]);
        $reviewer = User::factory()->create(['role' => UserRole::Admin]);
        app(ProfileUpdateRequestService::class)->approve($signature->request, $reviewer);

        return $signature->refresh();
    }

    private function manifest(Flight $flight, User $pilot, User $preparer, bool $cargo): FlightManifest
    {
        return app(FlightManifestService::class)->save($flight, $preparer, [
            'aircraft_owner_name' => 'TEST OWNER', 'aircraft_owner_contact_number' => '1234567',
            'home_base_hangar' => 'RPUS', 'coa_expiry_date' => now()->addYear()->toDateString(),
            'cargo_type' => $cargo ? 'Boxes' : null, 'cargo_quantity' => $cargo ? '2' : null,
            'total_cargo_weight' => $cargo ? '20.000' : '0.000',
            'persons' => [['full_name' => strtoupper($pilot->fullName()), 'nationality' => 'PH', 'classification' => 'pic']],
        ]);
    }

    private function authorize(Flight $flight, User $pilot, bool $signed): void
    {
        $this->actingAs($pilot);
        $service = app(PicAuthorizationService::class);
        $token = $service->createAuthorizationHandoff($flight->refresh());
        $service->authorizeFromHandoff($token, $pilot, AuthenticationEvidence::fromSession(Auth::guard('web')), $signed);
    }
}
