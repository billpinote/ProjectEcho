<?php

namespace Tests\Feature;

use App\Auth\AuthenticationEvidence;
use App\Domain\FlightPlans\Enums\FlightPlanStatus;
use App\Domain\FlightPlans\Services\AvsecVerificationService;
use App\Domain\FlightPlans\Services\FilingAuthorizationService;
use App\Domain\FlightPlans\Services\FlightManifestService;
use App\Domain\FlightPlans\Services\FlightPlanMutationService;
use App\Domain\FlightPlans\Services\FlightPlanPdfService;
use App\Domain\FlightPlans\Services\FlightPlanQrPayloadService;
use App\Domain\FlightPlans\Services\ManifestPdfService;
use App\Domain\FlightPlans\Services\PicAuthorizationService;
use App\Domain\FlightPlans\Support\FlightStatusDisplay;
use App\Domain\Pilots\Enums\PilotLicenseType;
use App\Domain\Users\Enums\UserRole;
use App\Filament\Panels\Dispatch\Resources\AcceptedFlights\Pages\ListAcceptedFlights;
use App\Filament\Panels\Dispatch\Resources\Flights\Pages\EditFlight;
use App\Filament\Panels\Pilot\Pages\EditFlightManifest;
use App\Filament\Panels\Pilot\Pages\ScanAuthorizationQr;
use App\Filament\Panels\Pilot\Resources\MyCurrentFlights\Pages\ListMyCurrentFlights;
use App\Models\Flight;
use App\Models\FlightManifest;
use App\Models\Operator;
use App\Models\PilotSignature;
use App\Models\User;
use App\Services\ProfileUpdates\ProfileUpdateRequestService;
use Carbon\CarbonInterface;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FlightFilingWorkflowRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        Filament::setCurrentPanel('pilot');
    }

    #[DataProvider('readerRoles')]
    public function test_role_visibility_and_downloads_share_flight_specific_access(UserRole $role): void
    {
        [$flight, $pic, $creator] = $this->filing();
        $this->manifest($flight, $pic, $creator);
        $actor = User::factory()->create(['role' => $role, 'operator_id' => $flight->operator_id, 'station' => 'RPUS']);
        $this->actingAs($actor);
        $this->assertTrue(Flight::query()->visibleTo($actor)->whereKey($flight)->exists());
        $events = $flight->events()->count();
        foreach (['flights.view', 'flights.manifest.pdf', 'flights.pdf.download', 'flights.qr'] as $route) {
            $response = $this->get(route($route, $flight));
            if ($route === 'flights.manifest.pdf' && $role === UserRole::Pilot) {
                $response->assertForbidden();
            } else {
                $response->assertOk();
            }
        }
        $this->assertSame($events, $flight->events()->count());
        $this->assertNull($flight->fresh()->reviewed_at);
        $this->post(route('flights.accept', $flight))->assertForbidden();
    }

    public static function readerRoles(): array
    {
        return array_map(fn ($role) => [$role], [UserRole::Pilot, UserRole::Dispatch, UserRole::Avsec,
            UserRole::Atmo, UserRole::AtsHq, UserRole::Artisan, UserRole::Admin]);
    }

    public function test_same_operator_pilot_whose_identity_does_not_match_manifest_cannot_sign(): void
    {
        [$flight, $pic, $creator] = $this->filing(true);
        $this->manifest($flight, $pic, $creator);
        [$unused, $other] = $this->filing();
        $other->update(['operator_id' => $flight->operator_id]);
        $before = $flight->fresh()->getAttributes();
        try {
            $this->sign($flight, $other);
            $this->fail('The manifest identifies a different PIC.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('manifest', $exception->errors());
        }
        $flight->refresh();
        $this->assertNull($flight->pilot_in_command_user_id);
        $this->assertSame($before['revision_number'], $flight->revision_number);
        $this->assertSame(0, $flight->events()->where('event_type', 'pic_authorized')->count());
    }

    public function test_legacy_awaiting_record_with_old_complete_signature_requires_fresh_explicit_decision(): void
    {
        [$flight, $pic, $creator] = $this->filing();
        $this->manifest($flight, $pic, $creator);
        $this->sign($flight, $pic);
        $old = $flight->filingAuthorizations()->sole();
        $preserved = $old->getRawOriginal();
        $flight->refresh()->forceFill(['status' => FlightPlanStatus::AwaitingPic])->saveQuietly();
        $this->assertFalse($flight->fresh()->isPicAuthorizationCurrent());
        $atmo = User::factory()->create(['role' => UserRole::Atmo, 'station' => 'RPUS']);
        $this->assertFalse($atmo->can('reject', $flight->fresh()));
        $this->assertFalse($atmo->can('accept', $flight->fresh()));
        $this->assertSame('For PIC Signature', FlightStatusDisplay::badge($flight->fresh(), UserRole::Pilot)['label']);
        $response = $this->actingAs($pic)->get(route('filing.review-self', $flight))->assertRedirect();
        $token = basename(parse_url($response->headers->get('Location'), PHP_URL_PATH));
        $this->post(route('filing.authorize-self', $token), ['apply_signature' => 1])->assertRedirect();
        $this->assertSame(FlightPlanStatus::Pending, $flight->fresh()->status);
        $this->assertSame(2, $flight->events()->where('event_type', 'pic_authorized')->count());
        $this->assertEquals($preserved, $old->fresh()->getRawOriginal());
    }

    public function test_reported_missing_manifest_partial_authorization_can_be_recovered_without_reusing_old_evidence(): void
    {
        [$flight, $pic, $creator] = $this->filing();
        $packages = app(FilingAuthorizationService::class);
        $legacy = $packages->request($flight, ['flight_plan']);
        $packages->decide($legacy, $flight, $pic, true, null, $pic->approvedSignature);
        $original = $legacy->getRawOriginal();
        $stale = app(FlightPlanQrPayloadService::class)->buildPayload($flight);
        $this->manifest($flight, $pic, $creator);
        $this->actingAs($pic);
        Livewire::test(ScanAuthorizationQr::class)->set('payload', $stale)
            ->assertSet('recoveryUrl', route('filing.review-self', $flight));
        $response = $this->get(route('filing.review-self', $flight))->assertRedirect();
        $token = basename(parse_url($response->headers->get('Location'), PHP_URL_PATH));
        $this->post(route('filing.authorize-self', $token), ['apply_signature' => 1])->assertRedirect();
        $this->assertTrue($flight->fresh()->canSubmitToAtc());
        $this->assertCount(2, $flight->filingAuthorizations()->latest('id')->firstOrFail()->pdf_documents);
        $this->assertEquals($original, $legacy->fresh()->getRawOriginal());
    }

    public function test_creator_livewire_edit_preserves_trusted_context_and_invalidates_signature(): void
    {
        [$flight, $pic, $creator] = $this->filing(true);
        $this->manifest($flight, $pic, $creator);
        $this->sign($flight, $pic);
        $revision = $flight->fresh()->revision_number;
        Filament::setCurrentPanel('dispatch');
        $date = $flight->date_of_flight;
        $date = $date instanceof CarbonInterface ? $date->format('Y-m-d') : substr((string) $date, 0, 10);
        Livewire::actingAs($creator)->test(EditFlight::class, ['record' => $flight->id])->assertOk()
            ->fillForm(['date_of_flight' => $date, 'aircraft_identification' => 'RPC123',
                'flight_rules' => 'V', 'type_of_flight' => 'G', 'number' => '1', 'type_of_aircraft' => 'C172',
                'wake_turbulence_cat' => 'L', 'equipment_10a' => 'S', 'equipment_10b' => 'C',
                'departure_aerodrome' => 'RPUS', 'destination_aerodrome' => 'RPLL', 'proposed_time' => '1430',
                'cruising_speed' => 'N0100', 'level' => 'VFR', 'route' => 'UPDATED', 'total_eet' => '0100',
                'endurance' => '0300', 'persons_on_board' => '1', 'other_information' => 'DOF/'.str_replace('-', '', $date),
                'dinghies_enabled' => false, 'pilot_in_command' => 'FORGED PIC', 'pilot_license_no' => 'FORGED'])
            ->call('save')->assertHasNoFormErrors();
        $flight->refresh();
        $this->assertSame('UPDATED', $flight->route);
        $this->assertSame($pic->id, $flight->pilot_in_command_user_id);
        $this->assertSame(strtoupper($pic->fullName()), $flight->pilot_in_command);
        $this->assertSame($creator->operator_id, $flight->operator_id);
        $this->assertSame($creator->id, $flight->prepared_by_user_id);
        $this->assertGreaterThan($revision, $flight->revision_number);
        $this->assertFalse($flight->isPicAuthorizationCurrent());
        $this->assertSame(FlightPlanStatus::AwaitingPic, $flight->status);
        $peer = User::factory()->create(['role' => UserRole::Dispatch, 'operator_id' => $creator->operator_id]);
        Livewire::actingAs($peer)->test(EditFlight::class, ['record' => $flight->id])->assertForbidden();
    }

    public function test_rejection_audit_failure_rolls_back_the_status_transition(): void
    {
        [$flight, $pic, $creator] = $this->filing();
        $this->manifest($flight, $pic, $creator);
        $this->sign($flight, $pic);
        $events = $flight->events()->count();
        $actor = User::factory()->create(['role' => UserRole::Atmo, 'station' => 'RPUS']);
        DB::unprepared("CREATE TRIGGER reject_audit_failure BEFORE INSERT ON flight_plan_events WHEN NEW.event_type = 'atc_rejected' BEGIN SELECT RAISE(ABORT, 'Audit unavailable'); END");
        try {
            app(FlightPlanMutationService::class)->reject($flight, $actor, 'AT', 'Review required');
            $this->fail('Rejection was committed without its audit event.');
        } catch (QueryException) {
            $this->assertSame(FlightPlanStatus::Pending, $flight->fresh()->status);
            $this->assertSame($events, $flight->events()->count());
        } finally {
            DB::unprepared('DROP TRIGGER reject_audit_failure');
        }
    }

    public function test_successful_scanner_authorization_links_to_current_record_instead_of_consumed_handoff(): void
    {
        [$flight, $pic, $creator] = $this->filing(true);
        $this->manifest($flight, $pic, $creator);
        $oldQr = app(FlightPlanQrPayloadService::class)->buildPayload($flight->fresh());
        Livewire::actingAs($pic)->test(ScanAuthorizationQr::class)->set('payload', $oldQr)
            ->call('authorizeAndSignAsPic')->assertHasNoErrors()
            ->assertSet('picAuthorizationHandoffToken', null)
            ->assertSet('matchedFlight.view_url', route('flights.view', $flight))
            ->assertSet('matchedFlight.status_label', 'For AVSEC Clearance');
        $this->get(route('flights.view', $flight))->assertOk();
        $snapshot = app(FlightPlanQrPayloadService::class)->parsePayload($oldQr)['snapshot'];
        $this->assertFalse(app(FlightPlanQrPayloadService::class)->snapshotMatchesFlight($snapshot, $flight->fresh()));
    }

    public function test_stored_pdf_refresh_failure_does_not_misreport_a_committed_authorization_as_failed(): void
    {
        [$flight, $pic, $creator] = $this->filing();
        $this->manifest($flight, $pic, $creator);
        $this->partialMock(FlightPlanPdfService::class)
            ->shouldReceive('regenerate')->once()->andThrow(new \RuntimeException('Stored PDF unavailable'));
        $signed = $this->sign($flight, $pic);
        $this->assertTrue($signed->canSubmitToAtc());
        $this->assertCount(2, $signed->filingAuthorizations()->sole()->pdf_documents);
        $this->assertSame(1, $signed->events()->where('event_type', 'pic_authorized')->count());
    }

    public function test_canonical_document_render_failure_rolls_back_authorization_and_preserves_qr(): void
    {
        [$flight, $pic, $creator] = $this->filing();
        $this->manifest($flight, $pic, $creator);
        $service = app(PicAuthorizationService::class);
        $token = $service->createAuthorizationHandoff($flight, true);
        $payloads = app(FlightPlanQrPayloadService::class);
        $snapshot = $payloads->parsePayload($payloads->buildPayload($flight->fresh()))['snapshot'];
        $this->mock(ManifestPdfService::class)
            ->shouldReceive('render')->once()->andThrow(new \RuntimeException('Canonical PDF unavailable'));
        try {
            $service->authorizeFromHandoff($token, $pic, applySignature: true, selfPrepared: true);
            $this->fail('A partial package was authorized.');
        } catch (\RuntimeException) {
            $this->assertTrue($payloads->snapshotMatchesFlight($snapshot, $flight->fresh()));
            $this->assertNotNull($service->resolveAuthorizationHandoff($token));
            $this->assertSame('pending', $flight->filingAuthorizations()->sole()->status);
            $this->assertSame(0, $flight->events()->where('event_type', 'pic_authorized')->count());
        }
    }

    public function test_peer_dispatch_cannot_record_timestamps_on_an_accepted_filing(): void
    {
        [$flight, $pic, $creator] = $this->filing(true);
        $this->manifest($flight, $pic, $creator);
        $this->sign($flight, $pic);
        $officer = User::factory()->create(['role' => UserRole::Avsec, 'station' => 'RPUS']);
        app(AvsecVerificationService::class)->confirm(app(FlightPlanQrPayloadService::class)->buildPayload($flight->fresh()), $officer, true);
        $atmo = User::factory()->create(['role' => UserRole::Atmo, 'station' => 'RPUS']);
        app(FlightPlanMutationService::class)->accept($flight, $atmo, 'AT');
        $flight->refresh();
        $peer = User::factory()->create(['role' => UserRole::Dispatch, 'operator_id' => $creator->operator_id]);
        $this->assertTrue($creator->can('updateStartUpTime', $flight));
        $this->assertFalse($peer->can('updateStartUpTime', $flight));
        Filament::setCurrentPanel('dispatch');
        Livewire::actingAs($peer)->test(ListAcceptedFlights::class)
            ->call('confirmStartUpNow', $flight->id)->assertForbidden();
        $this->assertNull($flight->fresh()->time_start_up);
    }

    public function test_review_package_uses_current_revision_even_when_caller_model_is_stale(): void
    {
        [$flight, $pic, $creator] = $this->filing();
        $this->manifest($flight, $pic, $creator);
        $this->sign($flight, $pic);
        $stale = $flight->fresh();
        app(FlightManifestService::class)->save($flight, $creator, ['remarks' => 'Revised manifest']);
        $package = app(FilingAuthorizationService::class)->request($stale);
        $this->assertSame($flight->fresh()->revision_number, $package->flight_revision);
        $this->assertSame($package->flight_revision, $package->documents['flight_plan']['revision']);
    }

    private function filing(bool $representative = false): array
    {
        $operator = Operator::factory()->create();
        $pic = User::factory()->create(['role' => UserRole::Pilot, 'operator_id' => $operator->id]);
        $pic->pilotProfile()->create(['license_type' => PilotLicenseType::CommercialPilot, 'license_number' => 'TEST-123',
            'ratings' => 'C172', 'license_expiry_date' => now()->addYears(2)->toDateString()]);
        $signature = PilotSignature::factory()->create(['user_id' => $pic->id]);
        app(ProfileUpdateRequestService::class)->approve($signature->request, User::factory()->create(['role' => UserRole::Admin]));
        $creator = $representative ? User::factory()->create(['role' => UserRole::Dispatch, 'operator_id' => $operator->id]) : $pic;
        $flight = Flight::factory()->create(['operator_id' => $operator->id, 'prepared_by_user_id' => $creator->id,
            'filed_by_user_id' => $creator->id, 'pilot_id' => $representative ? null : $pic->id,
            'pilot_in_command_user_id' => $representative ? null : $pic->id,
            'pilot_in_command' => $representative ? null : strtoupper($pic->fullName()),
            'pilot_license_no' => $representative ? null : 'CPL-TEST-123',
            'license_expiry_date' => $representative ? null : now()->addYears(2)->toDateString(),
            'status' => FlightPlanStatus::AwaitingPic]);

        return [$flight->refresh(), $pic, $creator];
    }

    private function manifest(Flight $flight, User $pic, User $creator, array $extra = []): FlightManifest
    {
        $this->actingAs($creator);

        return app(FlightManifestService::class)->save($flight, $creator, [
            'aircraft_owner_name' => 'Owner', 'aircraft_owner_contact_number' => '1234567', 'home_base_hangar' => 'RPUS',
            'coa_expiry_date' => now()->addYear()->toDateString(), 'total_cargo_weight' => 0,
            'persons' => [['full_name' => strtoupper($pic->fullName()), 'nationality' => 'PH', 'classification' => 'pic']], ...$extra,
        ]);
    }

    private function sign(Flight $flight, User $pic): Flight
    {
        $this->actingAs($pic);
        $selfPrepared = (int) $flight->prepared_by_user_id === (int) $pic->id;
        $service = app(PicAuthorizationService::class);
        $token = $service->createAuthorizationHandoff($flight, $selfPrepared);

        return $service->authorizeFromHandoff($token, $pic, AuthenticationEvidence::fromSession(Auth::guard()), true, $selfPrepared);
    }

    public function test_new_filing_requires_manifest_then_one_complete_package_signature(): void
    {
        [$flight, $pic, $creator] = $this->filing();
        $this->assertSame('Manifest Required', FlightStatusDisplay::badge($flight, UserRole::Pilot)['label']);
        $this->assertFalse($flight->isPicAuthorizationCurrent());
        $this->manifest($flight, $pic, $creator);
        $this->assertSame('For PIC Signature', FlightStatusDisplay::badge($flight->fresh(), UserRole::Pilot)['label']);
        $signed = $this->sign($flight->fresh(), $pic);
        $this->assertSame(FlightPlanStatus::Pending, $signed->status);
        $this->assertSame('For AVSEC Clearance', FlightStatusDisplay::badge($signed, UserRole::Pilot)['label']);
        $package = $signed->filingAuthorizations()->sole();
        $this->assertCount(2, $package->pdf_documents);
        $this->assertNotNull($package->pilot_signature_id);
        $this->assertSame(1, $signed->events()->where('event_type', 'pic_authorized')->count());
    }

    #[DataProvider('incompleteCases')]
    public function test_incomplete_manifest_cannot_authorize_or_change_qr_or_consume_handoff(string $case): void
    {
        [$flight, $pic, $creator] = $this->filing();
        if ($case !== 'missing') {
            $this->manifest($flight, $pic, $creator, $case === 'nationality'
                ? ['persons' => [['full_name' => $flight->pilot_in_command, 'nationality' => null, 'classification' => 'pic']]]
                : ['total_cargo_weight' => 5, 'cargo_type' => null]);
        }
        $service = app(PicAuthorizationService::class);
        $this->actingAs($pic);
        $token = $service->createAuthorizationHandoff($flight, true);
        $before = $flight->fresh()->getAttributes();
        $qr = app(FlightPlanQrPayloadService::class)->parsePayload(app(FlightPlanQrPayloadService::class)->buildPayload($flight->fresh()));
        try {
            $service->authorizeFromHandoff($token, $pic, AuthenticationEvidence::fromSession(Auth::guard()), true, true);
            $this->fail('Incomplete manifest was authorized.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('manifest', $exception->errors());
        }
        $this->assertSame($before, $flight->fresh()->getAttributes());
        $this->assertNotNull($service->resolveAuthorizationHandoff($token));
        $this->assertTrue(app(FlightPlanQrPayloadService::class)->snapshotMatchesFlight($qr['snapshot'], $flight->fresh()));
        $this->assertSame('pending', $flight->filingAuthorizations()->sole()->status);
        $this->assertSame(0, $flight->events()->where('event_type', 'pic_authorized')->count());
    }

    public static function incompleteCases(): array
    {
        return [['missing'], ['nationality'], ['cargo']];
    }

    public function test_manifest_page_has_one_disabled_authorization_action_until_complete(): void
    {
        [$flight, $pic, $creator] = $this->filing();
        Livewire::actingAs($pic)->test(EditFlightManifest::class, ['flight' => $flight])
            ->assertActionDisabled('signFiling')->assertSeeText('complete the linked flight manifest');
        $this->manifest($flight, $pic, $creator);
        $page = Livewire::actingAs($pic)->test(EditFlightManifest::class, ['flight' => $flight->fresh()])
            ->assertActionEnabled('signFiling')->assertDontSeeText('Authorize Manifest Signature')->assertDontSeeText('Authorize without signature image');
        $page->mountAction('signFiling')->callMountedAction()->assertHasNoErrors();
        $this->assertTrue($flight->fresh()->canSubmitToAtc());
    }

    public function test_duplicate_authorization_is_denied_without_new_audit_or_signature_records(): void
    {
        [$flight, $pic, $creator] = $this->filing();
        $this->manifest($flight, $pic, $creator);
        $this->sign($flight, $pic);
        $events = $flight->events()->count();
        $this->actingAs($pic)->get(route('filing.review-self', $flight))->assertForbidden();
        $this->assertSame(1, $flight->filingAuthorizations()->count());
        $this->assertSame($events, $flight->events()->count());
    }

    public function test_eligible_same_operator_pic_handoff_and_wrong_operator_denial(): void
    {
        [$flight, $pic, $creator] = $this->filing(true);
        $this->manifest($flight, $pic, $creator);
        $other = User::factory()->create(['role' => UserRole::Pilot, 'operator_id' => Operator::factory()->create()->id]);
        $this->actingAs($other)->get(route('filing.review-self', $flight))->assertForbidden();
        $signed = $this->sign($flight->fresh(), $pic);
        $this->assertSame($pic->id, $signed->pilot_in_command_user_id);
        $this->assertTrue($signed->canSubmitToAtc());
        $this->assertFalse($creator->can('accept', $signed));
    }

    public function test_dispatch_role_cannot_sign_even_with_a_pilot_profile(): void
    {
        [$flight, $pic, $creator] = $this->filing(true);
        $this->manifest($flight, $pic, $creator);
        $creator->pilotProfile()->create(['license_type' => PilotLicenseType::CommercialPilot, 'license_number' => 'DISPATCH', 'license_expiry_date' => now()->addYear()->toDateString()]);
        $this->expectException(ValidationException::class);
        app(PicAuthorizationService::class)->authorizeFromPayload(app(FlightPlanQrPayloadService::class)->buildPayload($flight), $creator);
    }

    public function test_stale_qr_recovery_requires_current_review_and_preserves_historical_evidence(): void
    {
        [$flight, $pic, $creator] = $this->filing();
        $this->manifest($flight, $pic, $creator);
        $this->sign($flight, $pic);
        $original = $flight->filingAuthorizations()->sole();
        $evidence = $original->getRawOriginal();
        $oldQr = app(FlightPlanQrPayloadService::class)->buildPayload($flight->fresh());
        app(FlightManifestService::class)->save($flight, $creator, ['remarks' => 'Material revision']);
        $this->assertFalse($flight->fresh()->isPicAuthorizationCurrent());
        $this->assertNull(app(FilingAuthorizationService::class)->signatureBytes($flight->fresh()));
        try {
            app(PicAuthorizationService::class)->resolveAccessibleFlightFromPayload($oldQr, $pic);
            $this->fail('Stale QR was accepted.');
        } catch (ValidationException) {
            $this->assertFalse($flight->fresh()->canSubmitToAtc());
        }
        $response = $this->actingAs($pic)->get(route('filing.review-self', $flight))->assertRedirect();
        $this->get($response->headers->get('Location'))->assertOk()->assertSeeText('Authorize & Sign Filing');
        $token = basename(parse_url($response->headers->get('Location'), PHP_URL_PATH));
        $this->post(route('filing.authorize-self', $token), ['apply_signature' => 1])->assertRedirect();
        $this->assertTrue($flight->fresh()->canSubmitToAtc());
        $this->assertEquals($evidence, $original->fresh()->getRawOriginal());
        $this->assertSame(2, $flight->events()->where('event_type', 'pic_authorized')->count());
        $this->assertSame(1, $flight->events()->where('event_type', 'filing_authorization_invalidated')->count());
    }

    public function test_avsec_then_atmo_acceptance_does_not_grant_startup_clearance(): void
    {
        [$flight, $pic, $creator] = $this->filing();
        $this->manifest($flight, $pic, $creator);
        $this->sign($flight, $pic);
        $atmo = User::factory()->create(['role' => UserRole::Atmo, 'station' => 'RPUS']);
        $this->actingAs($atmo)->post(route('flights.accept', $flight))->assertForbidden();
        $officer = User::factory()->create(['role' => UserRole::Avsec, 'station' => 'RPUS']);
        app(AvsecVerificationService::class)->confirm(app(FlightPlanQrPayloadService::class)->buildPayload($flight->fresh()), $officer, true);
        $this->assertSame('For ATMO Signature', FlightStatusDisplay::badge($flight->fresh(), UserRole::Pilot)['label']);
        $this->actingAs($atmo)->post(route('flights.accept', $flight))->assertRedirect();
        $flight->refresh();
        $this->assertSame(FlightPlanStatus::Accepted, $flight->status);
        $this->assertSame('Filed Successfully', FlightStatusDisplay::badge($flight, UserRole::Pilot)['label']);
        $this->assertNull($flight->time_start_up);
        $this->assertNull($flight->time_block_off);
        $this->actingAs($pic)->get(route('flights.view', $flight))->assertOk()->assertSeeText(FlightStatusDisplay::ACCEPTANCE_NOTICE);
        $this->assertFalse($pic->can('updateStartUpTime', $flight));
        Livewire::actingAs($pic)->test(ListMyCurrentFlights::class)
            ->assertSeeText('Filed Successfully')->assertSeeText(FlightStatusDisplay::ACCEPTANCE_NOTICE);
    }

    public function test_dispatch_creator_edit_other_dispatch_read_only_and_operator_isolation(): void
    {
        [$flight, $pic, $creator] = $this->filing(true);
        $manifest = $this->manifest($flight, $pic, $creator);
        $peer = User::factory()->create(['role' => UserRole::Dispatch, 'operator_id' => $creator->operator_id]);
        $other = User::factory()->create(['role' => UserRole::Dispatch, 'operator_id' => Operator::factory()->create()->id]);
        $this->assertTrue($creator->can('update', $flight));
        $this->assertTrue($creator->can('update', $manifest));
        $this->assertTrue($peer->can('view', $manifest));
        $this->assertFalse($peer->can('update', $manifest));
        $this->assertFalse($peer->can('update', $flight));
        $this->actingAs($peer)->get(route('flights.manifest.edit', $flight))->assertRedirect();
        $this->put(route('flights.manifest.update', $flight), ['remarks' => 'Forged'])->assertForbidden();
        $this->actingAs($other)->get(route('flights.manifest.pdf', $flight))->assertForbidden();
        $this->assertFalse(Flight::query()->visibleTo($other)->whereKey($flight)->exists());
        $this->assertNull($manifest->fresh()->remarks);
    }
}
