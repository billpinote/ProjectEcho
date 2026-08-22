<?php

namespace Tests\Feature;

use App\Domain\FlightPlans\Enums\FlightPlanStatus;
use App\Domain\FlightPlans\Services\FlightPlanQrPayloadService;
use App\Domain\FlightPlans\Services\PicAuthorizationService;
use App\Domain\FlightPlans\Support\FlightAccess;
use App\Domain\Pilots\Enums\PilotLicenseType;
use App\Domain\Users\Enums\UserRole;
use App\Filament\Shared\Resources\Flights\FlightResource;
use App\Filament\Panels\Pilot\Pages\ScanAuthorizationQr;
use App\Filament\Shared\Pages\ImportScanQr;
use App\Models\Flight;
use App\Models\Operator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Livewire\Livewire;

class PicAuthorizationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('eligibleLicenseTypes')]
    public function test_verified_ppl_cpl_and_atpl_holders_can_authorize(string $licenseType): void
    {
        $authorizer = $this->user(UserRole::Pilot, $licenseType);
        $flight = $this->awaitingFlight($authorizer);

        $authorized = app(PicAuthorizationService::class)->authorizeFromPayload($this->payload($flight), $authorizer);

        $this->assertSame($authorizer->id, $authorized->pilot_in_command_user_id);
        $this->assertSame($authorizer->id, $authorized->pic_authorized_by_user_id);
        $this->assertSame('QR', $authorized->pic_authorization_method);
        $this->assertTrue($authorized->isPicAuthorizationCurrent());
        $this->assertTrue($authorized->canSubmitToAtc());
        $this->assertSame(FlightPlanStatus::Pending, $authorized->status);
    }

    #[DataProvider('eligibleLicenseTypes')]
    public function test_same_operator_eligible_pilot_can_authorize_another_pilots_flight(string $licenseType): void
    {
        $operator = Operator::factory()->create();
        $authorizer = $this->user(UserRole::Pilot, $licenseType, $operator);
        $filedBy = $this->user(UserRole::Pilot, null, $operator);
        $flight = $this->awaitingFlight($authorizer, [
            'filed_by_user_id' => $filedBy->id,
            'operator_id' => $operator->id,
        ]);

        $this->assertFalse(FlightAccess::canView($authorizer, $flight));
        $this->assertTrue(FlightAccess::canAccessPicAuthorization($authorizer, $flight));
        $this->assertTrue(app(PicAuthorizationService::class)->authorizeFromPayload($this->payload($flight), $authorizer)->canSubmitToAtc());
    }

    public function test_same_operator_pilot_loads_another_pilots_qr_through_the_scanner(): void
    {
        $operator = Operator::factory()->create();
        $authorizer = $this->user(UserRole::Pilot, PilotLicenseType::AirlineTransportPilot->value, $operator);
        $filedBy = $this->user(UserRole::Pilot, PilotLicenseType::CommercialPilot->value, $operator);
        $flight = $this->awaitingFlight($authorizer, [
            'filed_by_user_id' => $filedBy->id,
            'operator_id' => $operator->id,
        ]);

        $this->actingAs($authorizer);

        $component = Livewire::test(ScanAuthorizationQr::class)
            ->set('payload', $this->payload($flight))
            ->assertSet('matchedFlight.id', $flight->id)
            ->assertSeeText('PIC Authorization Required')
            ->assertSeeText('Authorize as PIC')
            ->assertSeeText('Decline Authorization');

        $previewToken = array_key_last((array) session('scanned_flight_plan_previews'));
        $previewUrl = route('flightplan.pic-authorization.preview', ['token' => $previewToken]);

        $component->assertSet('matchedFlight.view_url', $previewUrl);

        $this->get($previewUrl)
            ->assertOk()
            ->assertSee('FLIGHT PLAN', false)
            ->assertSee('BACK TO PIC AUTHORIZATION SCANNER', false)
            ->assertDontSee('<button', false)
            ->assertDontSee('<form method="POST"', false);

        $this->get(route('flights.view', $flight))->assertForbidden();
        $this->assertFalse(FlightResource::getEloquentQuery()->whereKey($flight)->exists());
    }

    public function test_different_operator_pilot_is_rejected_by_the_scanner_before_authorization(): void
    {
        $flightOperator = Operator::factory()->create();
        $authorizer = $this->user(UserRole::Pilot, PilotLicenseType::AirlineTransportPilot->value, Operator::factory()->create());
        $flight = $this->awaitingFlight($authorizer, ['operator_id' => $flightOperator->id]);

        $this->actingAs($authorizer);

        Livewire::test(ScanAuthorizationQr::class)
            ->set('payload', $this->payload($flight))
            ->assertSet('matchedFlight', null);
    }

    public function test_different_operator_cannot_use_a_pic_preview_token(): void
    {
        $operator = Operator::factory()->create();
        $authorizer = $this->user(UserRole::Pilot, PilotLicenseType::AirlineTransportPilot->value, $operator);
        $filedBy = $this->user(UserRole::Pilot, null, $operator);
        $flight = $this->awaitingFlight($authorizer, [
            'filed_by_user_id' => $filedBy->id,
            'operator_id' => $operator->id,
        ]);

        $this->actingAs($authorizer);
        Livewire::test(ScanAuthorizationQr::class)->set('payload', $this->payload($flight));
        $previewToken = array_key_last((array) session('scanned_flight_plan_previews'));

        $otherPilot = $this->user(UserRole::Pilot, PilotLicenseType::PrivatePilot->value, Operator::factory()->create());

        $this->actingAs($otherPilot)
            ->get(route('flightplan.pic-authorization.preview', ['token' => $previewToken]))
            ->assertForbidden();
    }

    public function test_preparer_can_load_authorization_preview_but_has_no_decision_controls(): void
    {
        $preparer = $this->user(UserRole::Pilot, PilotLicenseType::CommercialPilot->value);
        $flight = $this->awaitingFlight($preparer, ['prepared_by_user_id' => $preparer->id]);

        $this->actingAs($preparer);

        Livewire::test(ScanAuthorizationQr::class)
            ->set('payload', $this->payload($flight))
            ->assertSet('matchedFlight.id', $flight->id)
            ->assertSeeText('You prepared this flight plan')
            ->assertDontSeeText('Authorize as PIC')
            ->assertDontSeeText('Decline Authorization');

        $previewToken = array_key_last((array) session('scanned_flight_plan_previews'));

        $this->get(route('flightplan.pic-authorization.preview', ['token' => $previewToken]))
            ->assertOk()
            ->assertSee('BACK TO PIC AUTHORIZATION SCANNER', false);
    }

    public function test_pic_authorization_preview_rejects_missing_token(): void
    {
        $pilot = $this->user(UserRole::Pilot, PilotLicenseType::CommercialPilot->value);

        $this->actingAs($pilot)
            ->get(route('flightplan.pic-authorization.preview', ['token' => 'missing-token']))
            ->assertForbidden();
    }

    public function test_pic_authorization_handoff_can_be_opened_from_a_separate_session(): void
    {
        $operator = Operator::factory()->create();
        $scanner = $this->user(UserRole::Dispatch, null, $operator);
        $pilot = $this->user(UserRole::Pilot, PilotLicenseType::CommercialPilot->value, $operator);
        $flight = $this->awaitingFlight($scanner, [
            'operator_id' => $operator->id,
            'prepared_by_user_id' => $scanner->id,
            'pilot_in_command_user_id' => null,
        ]);
        $token = app(PicAuthorizationService::class)->createAuthorizationHandoff($flight);

        $this->actingAs($pilot)
            ->withSession([])
            ->get(route('flightplan.pic-authorization.preview', ['token' => $token]))
            ->assertOk()
            ->assertSee('FLIGHT PLAN', false);

        Livewire::actingAs($pilot)
            ->test(ScanAuthorizationQr::class)
            ->set('picAuthorizationHandoffToken', $token)
            ->call('authorizeAsPic')
            ->assertHasNoErrors();

        $this->assertTrue($flight->refresh()->isPicAuthorizationCurrent());
    }

    public function test_jesse_to_chezka_current_qr_authorization_succeeds(): void
    {
        $operator = Operator::factory()->create();
        $jesse = $this->user(UserRole::Pilot, null, $operator);
        $chezka = $this->user(UserRole::Pilot, PilotLicenseType::CommercialPilot->value, $operator);
        $flight = $this->awaitingFlight($jesse, [
            'operator_id' => $operator->id,
            'prepared_by_user_id' => $jesse->id,
            'pilot_in_command_user_id' => null,
        ]);
        $payload = $this->payload($flight);
        $parsed = app(FlightPlanQrPayloadService::class)->parsePayload($payload);
        $this->assertIsArray($parsed);
        $this->assertSame([], app(FlightPlanQrPayloadService::class)->snapshotMismatches($parsed['snapshot'], $flight));

        $this->actingAs($chezka);
        Livewire::test(ScanAuthorizationQr::class)
            ->set('payload', $payload)
            ->call('authorizeAsPic')
            ->assertHasNoErrors();

        $this->assertTrue($flight->refresh()->isPicAuthorizationCurrent());
        $this->assertSame(FlightPlanStatus::Pending, $flight->status);
    }

    public function test_pic_authorization_regenerates_the_official_pdf_with_uppercase_credentials(): void
    {
        Storage::fake('public');
        $pilot = $this->user(UserRole::Pilot, PilotLicenseType::CommercialPilot->value);
        $pilot->forceFill(['first_name' => 'Jesse', 'last_name' => 'James'])->save();
        $pilot->refresh();
        $flight = $this->awaitingFlight($pilot);
        $pdfService = app(\App\Domain\FlightPlans\Services\FlightPlanPdfService::class);
        $path = $pdfService->regenerate($flight);
        $before = Storage::disk('public')->get($path);
        $token = app(PicAuthorizationService::class)->createAuthorizationHandoff($flight);

        app(PicAuthorizationService::class)->authorizeFromHandoff($token, $pilot);

        $flight->refresh();
        $expectedPilotName = strtoupper($pilot->fullName());
        $this->assertSame($expectedPilotName, $flight->pilot_in_command);
        $this->assertNotSame($before, Storage::disk('public')->get($path));
        $this->assertCount(1, Storage::disk('public')->allFiles('flight-plans'));
        $this->actingAs($pilot)
            ->get(route('flights.view', $flight))
            ->assertSee($expectedPilotName, false);
    }

    public function test_real_jesse_filing_to_chezka_authorization_reports_no_pre_authorization_mismatch(): void
    {
        Storage::fake('public');
        $operator = Operator::factory()->create(['name' => 'Jesse Air', 'short_name' => 'JSA']);
        $jesse = $this->user(UserRole::Pilot, null, $operator);
        $jesse->pilotProfile()->create([
            'license_type' => PilotLicenseType::StudentPilot,
            'license_number' => 'SPL-JESSE',
            'license_expiry_date' => now()->addYear()->toDateString(),
        ]);
        $jesse->refresh();
        $chezka = $this->user(UserRole::Pilot, PilotLicenseType::CommercialPilot->value, $operator);
        $date = now('Asia/Manila')->addDay()->toDateString();

        $this->actingAs($jesse)
            ->post(route('flightplan.store'), [
                'date_of_flight' => $date,
                'aircraft_identification' => 'JSA001',
                'flight_rules' => 'I',
                'type_of_flight' => 'S',
                'number' => '1',
                'type_of_aircraft' => 'C172',
                'wake_turbulence_cat' => 'L',
                'equipment_10a' => 'S',
                'equipment_10b' => 'C',
                'departure_aerodrome' => 'RPUS',
                'proposed_time' => '1430',
                'cruising_speed' => 'N100',
                'level' => 'F150',
                'route' => 'DCT',
                'destination_aerodrome' => 'RPLL',
                'total_eet' => '0130',
                'endurance' => '0400',
                'persons_on_board' => '2',
                'other_information' => 'DOF/'.str_replace('-', '', $date),
            ])
            ->assertRedirect(route('flightplan.preview'));

        $this->actingAs($jesse)
            ->post(route('flightplan.approve'))
            ->assertRedirect();

        $flight = Flight::query()->where('aircraft_identification', 'JSA001')->latest('id')->firstOrFail();
        $payload = app(FlightPlanQrPayloadService::class)->buildPayload($flight);
        $parsed = app(FlightPlanQrPayloadService::class)->parsePayload((string) $payload);
        $this->assertIsArray($parsed);
        $mismatches = app(FlightPlanQrPayloadService::class)->snapshotMismatches($parsed['snapshot'], $flight);
        $this->assertSame([], $mismatches, json_encode(array_map(
            fn (string $field): array => [
                'field' => $field,
                'qr' => $parsed['snapshot'][$field] ?? null,
                'db' => $flight->getAttribute($field),
            ],
            $mismatches,
        ), JSON_THROW_ON_ERROR));

        $this->actingAs($chezka)->withSession([])
            ->get(route('flightplan.pic-authorization.preview', [
                'token' => app(PicAuthorizationService::class)->createAuthorizationHandoff($flight),
            ]))
            ->assertOk();

        $this->actingAs($chezka);
        Livewire::test(ScanAuthorizationQr::class)
            ->set('payload', $payload)
            ->call('authorizeAsPic')
            ->assertHasNoErrors();
    }

    public function test_pic_authorization_rejects_material_changes_to_the_signed_qr_revision(): void
    {
        foreach (['route', 'destination_aerodrome', 'date_of_flight'] as $field) {
            $pilot = $this->user(UserRole::Pilot, PilotLicenseType::CommercialPilot->value);
            $flight = $this->awaitingFlight($pilot);
            $payload = $this->payload($flight);

            $flight->forceFill([
                $field => $field === 'date_of_flight'
                    ? now('Asia/Manila')->addDays(2)->toDateString()
                    : $flight->{$field}.'-CHANGED',
            ])->save();

            try {
                app(PicAuthorizationService::class)->authorizeFromPayload($payload, $pilot);
                $this->fail('A material flight-plan change must invalidate the signed QR payload.');
            } catch (ValidationException $exception) {
                $this->assertStringContainsString('stale', implode(' ', $exception->errors()['payload'] ?? []));
            }
        }
    }

    public function test_consumed_pic_authorization_handoff_cannot_be_reused(): void
    {
        $pilot = $this->user(UserRole::Pilot, PilotLicenseType::CommercialPilot->value);
        $flight = $this->awaitingFlight($pilot);
        $token = app(PicAuthorizationService::class)->createAuthorizationHandoff($flight);

        app(PicAuthorizationService::class)->authorizeFromHandoff($token, $pilot);

        $this->actingAs($pilot)
            ->get(route('flightplan.pic-authorization.preview', ['token' => $token]))
            ->assertForbidden();
    }

    public function test_revision_change_after_handoff_blocks_token_authorization(): void
    {
        $pilot = $this->user(UserRole::Pilot, PilotLicenseType::CommercialPilot->value);
        $flight = $this->awaitingFlight($pilot);
        $token = app(PicAuthorizationService::class)->createAuthorizationHandoff($flight);

        $flight->incrementRevisionNumber();

        $this->expectException(ValidationException::class);
        app(PicAuthorizationService::class)->authorizeFromHandoff($token, $pilot);
    }

    public function test_decline_consumes_the_pic_authorization_handoff(): void
    {
        $operator = Operator::factory()->create();
        $reviewer = $this->user(UserRole::Dispatch, null, $operator);
        $preparer = $this->user(UserRole::Dispatch, null, $operator);
        $flight = $this->awaitingFlight($reviewer, [
            'operator_id' => $operator->id,
            'prepared_by_user_id' => $preparer->id,
        ]);
        $token = app(PicAuthorizationService::class)->createAuthorizationHandoff($flight);

        app(PicAuthorizationService::class)->declineFromHandoff($token, $reviewer, 'Needs correction.');

        $this->assertNull($flight->refresh()->pic_authorization_token);
        $this->expectException(ValidationException::class);
        app(PicAuthorizationService::class)->declineFromHandoff($token, $reviewer);
    }

    public function test_tampered_or_expired_pic_authorization_handoff_is_rejected(): void
    {
        $pilot = $this->user(UserRole::Pilot, PilotLicenseType::CommercialPilot->value);
        $flight = $this->awaitingFlight($pilot);
        $token = app(PicAuthorizationService::class)->createAuthorizationHandoff($flight);

        $this->actingAs($pilot)
            ->get(route('flightplan.pic-authorization.preview', ['token' => substr_replace($token, 'x', 0, 1)]))
            ->assertForbidden();

        $flight->forceFill(['pic_authorization_token_expires_at' => now()->subMinute()])->saveQuietly();

        $this->get(route('flightplan.pic-authorization.preview', ['token' => $token]))
            ->assertForbidden();
    }

    public function test_atmo_saved_flight_preview_uses_atmo_back_destination(): void
    {
        $atmo = $this->user(UserRole::Atmo);
        $atmo->forceFill(['station' => 'RPUS'])->save();
        $flight = $this->awaitingFlight($atmo, [
            'status' => FlightPlanStatus::Pending,
            'prepared_by_user_id' => $atmo->id,
            'pilot_in_command_user_id' => $atmo->id,
        ]);

        $this->actingAs($atmo)
            ->get(route('flights.view', $flight))
            ->assertOk()
            ->assertSee('Back to Dashboard')
            ->assertSee('href="'.url('/atmo').'"', false)
            ->assertSee('echo-preview-dashboard-button', false)
            ->assertDontSee('<a href="'.url('/atmo').'">', false)
            ->assertDontSee('BACK TO PIC AUTHORIZATION SCANNER', false)
            ->assertDontSee('/admin', false);
    }

    public function test_pilot_saved_flight_preview_uses_pilot_dashboard_back_destination(): void
    {
        $pilot = $this->user(UserRole::Pilot, PilotLicenseType::CommercialPilot->value);
        $flight = $this->awaitingFlight($pilot, [
            'pilot_in_command_user_id' => $pilot->id,
        ]);

        $this->actingAs($pilot)
            ->get(route('flights.view', $flight))
            ->assertOk()
            ->assertSee('Back to Dashboard')
            ->assertSee('href="'.url('/pilot').'"', false);
    }

    public function test_dispatch_saved_flight_preview_uses_dispatch_dashboard_back_destination(): void
    {
        $dispatch = $this->user(UserRole::Dispatch);
        $flight = $this->awaitingFlight($dispatch, [
            'status' => FlightPlanStatus::Pending,
            'prepared_by_user_id' => $dispatch->id,
        ]);

        $this->actingAs($dispatch)
            ->get(route('flights.view', $flight))
            ->assertOk()
            ->assertSee('Back to Dashboard')
            ->assertSee('href="'.url('/dispatch').'"', false);
    }

    public function test_pic_authorization_preview_rejects_stale_handoff_after_revision_change(): void
    {
        $pilot = $this->user(UserRole::Pilot, PilotLicenseType::AirlineTransportPilot->value);
        $flight = $this->awaitingFlight($pilot);

        $this->actingAs($pilot);
        Livewire::test(ScanAuthorizationQr::class)->set('payload', $this->payload($flight));
        $previewToken = array_key_last((array) session('scanned_flight_plan_previews'));

        $flight->forceFill([
            'revision_number' => (int) ($flight->revision_number ?? 1) + 1,
        ])->save();

        $this->get(route('flightplan.pic-authorization.preview', ['token' => $previewToken]))
            ->assertForbidden();
    }

    public function test_normal_import_scanner_still_denies_another_pilots_flight(): void
    {
        $operator = Operator::factory()->create();
        $pilot = $this->user(UserRole::Pilot, PilotLicenseType::AirlineTransportPilot->value, $operator);
        $filedBy = $this->user(UserRole::Pilot, null, $operator);
        $flight = $this->awaitingFlight($pilot, [
            'filed_by_user_id' => $filedBy->id,
            'operator_id' => $operator->id,
        ]);

        $this->actingAs($pilot);

        Livewire::test(ImportScanQr::class)
            ->set('payload', $this->payload($flight))
            ->assertSet('matchedFlight', null);
    }

    #[DataProvider('eligibleLicenseTypes')]
    public function test_different_operator_eligible_pilot_is_denied(string $licenseType): void
    {
        $flightOperator = Operator::factory()->create();
        $authorizer = $this->user(UserRole::Pilot, $licenseType, Operator::factory()->create());
        $flight = $this->awaitingFlight($authorizer, ['operator_id' => $flightOperator->id]);

        try {
            app(PicAuthorizationService::class)->authorizeFromPayload($this->payload($flight), $authorizer);
            $this->fail('A pilot from another operator was allowed to authorize.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                "This flight plan belongs to another operator. PIC authorization is limited to eligible pilots associated with the flight's operator.",
                $exception->errors()['payload'][0],
            );
        }
    }

    public static function eligibleLicenseTypes(): array
    {
        return [[PilotLicenseType::PrivatePilot->value], [PilotLicenseType::CommercialPilot->value], [PilotLicenseType::AirlineTransportPilot->value]];
    }

    public function test_spl_cannot_authorize(): void
    {
        $authorizer = $this->user(UserRole::Pilot, PilotLicenseType::StudentPilot->value);
        $flight = $this->awaitingFlight($authorizer);

        $this->expectException(ValidationException::class);
        app(PicAuthorizationService::class)->authorizeFromPayload($this->payload($flight), $authorizer);
    }

    public function test_same_operator_spl_can_access_the_qr_but_cannot_authorize(): void
    {
        $operator = Operator::factory()->create();
        $authorizer = $this->user(UserRole::Pilot, PilotLicenseType::StudentPilot->value, $operator);
        $filedBy = $this->user(UserRole::Pilot, null, $operator);
        $flight = $this->awaitingFlight($authorizer, [
            'filed_by_user_id' => $filedBy->id,
            'operator_id' => $operator->id,
        ]);

        $this->assertTrue(FlightAccess::canAccessPicAuthorization($authorizer, $flight));

        try {
            app(PicAuthorizationService::class)->authorizeFromPayload($this->payload($flight), $authorizer);
            $this->fail('An SPL holder was allowed to authorize.');
        } catch (ValidationException $exception) {
            $this->assertSame('Only verified PPL, CPL, or ATPL holders may authorize as PIC.', $exception->errors()['payload'][0]);
        }
    }

    public function test_dispatch_and_operator_staff_without_eligible_profiles_cannot_authorize(): void
    {
        foreach ([UserRole::Dispatch, UserRole::OperatorStaff] as $role) {
            $user = $this->user($role);
            $flight = $this->awaitingFlight($user);

            try {
                app(PicAuthorizationService::class)->authorizeFromPayload($this->payload($flight), $user);
                $this->fail('An unqualified operational user authorized a flight plan.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_preparer_cannot_self_authorize(): void
    {
        $preparer = $this->user(UserRole::Pilot, PilotLicenseType::CommercialPilot->value);
        $flight = $this->awaitingFlight($preparer, ['prepared_by_user_id' => $preparer->id]);

        try {
            app(PicAuthorizationService::class)->authorizeFromPayload($this->payload($flight), $preparer);
            $this->fail('The preparer was allowed to authorize their own submission.');
        } catch (ValidationException $exception) {
            $this->assertSame('The preparer cannot authorize their own flight-plan submission.', $exception->errors()['payload'][0]);
        }
    }

    public function test_preparer_cannot_decline_their_own_submission(): void
    {
        $preparer = $this->user(UserRole::Pilot, PilotLicenseType::CommercialPilot->value);
        $flight = $this->awaitingFlight($preparer, ['prepared_by_user_id' => $preparer->id]);

        try {
            app(PicAuthorizationService::class)->declineFromPayload($this->payload($flight), $preparer, 'No.');
            $this->fail('The preparer was allowed to decline their own submission.');
        } catch (ValidationException $exception) {
            $this->assertSame('The preparer cannot authorize their own flight-plan submission.', $exception->errors()['payload'][0]);
            $this->assertNull($flight->refresh()->pic_authorization_status);
        }
    }

    public function test_preparer_can_open_but_has_no_pic_decision_controls(): void
    {
        $preparer = $this->user(UserRole::Pilot, PilotLicenseType::CommercialPilot->value);
        $flight = $this->awaitingFlight($preparer, ['prepared_by_user_id' => $preparer->id]);
        $page = new ScanAuthorizationQr;
        $page->matchedFlight = ['id' => $flight->id];

        $this->actingAs($preparer);
        $this->assertTrue($page->isPicAuthorizationPreparer());
        $this->assertFalse($page->canAuthorizeMatchedFlight());
    }

    public function test_awaiting_pic_flight_is_absent_from_atmo_pending_queue(): void
    {
        $flight = $this->awaitingFlight($this->user(UserRole::Pilot));

        $this->assertFalse(FlightResource::getEloquentQuery()->whereKey($flight)->exists());
        $this->assertTrue($flight->status === FlightPlanStatus::AwaitingPic);
    }

    public function test_invalid_qr_cannot_authorize(): void
    {
        $authorizer = $this->user(UserRole::Pilot, PilotLicenseType::PrivatePilot->value);
        $this->awaitingFlight($authorizer);

        $this->expectException(ValidationException::class);
        app(PicAuthorizationService::class)->authorizeFromPayload('not-a-signed-qr', $authorizer);
    }

    public function test_stale_qr_cannot_authorize_after_revision_change(): void
    {
        $authorizer = $this->user(UserRole::Pilot, PilotLicenseType::PrivatePilot->value);
        $flight = $this->awaitingFlight($authorizer);
        $payload = $this->payload($flight);
        $flight->incrementRevisionNumber();

        $this->expectException(ValidationException::class);
        app(PicAuthorizationService::class)->authorizeFromPayload($payload, $authorizer);
    }

    public function test_already_authorized_flight_cannot_be_authorized_again(): void
    {
        $authorizer = $this->user(UserRole::Pilot, PilotLicenseType::PrivatePilot->value);
        $flight = $this->awaitingFlight($authorizer);
        $payload = $this->payload($flight);
        app(PicAuthorizationService::class)->authorizeFromPayload($payload, $authorizer);

        $this->expectException(ValidationException::class);
        app(PicAuthorizationService::class)->authorizeFromPayload($payload, $authorizer);
    }

    public function test_decline_records_audit_state_without_deleting_the_flight(): void
    {
        $operator = Operator::factory()->create();
        $reviewer = $this->user(UserRole::Dispatch, null, $operator);
        $preparer = $this->user(UserRole::Dispatch, null, $operator);
        $flight = $this->awaitingFlight($reviewer, [
            'prepared_by_user_id' => $preparer->id,
            'operator_id' => $operator->id,
        ]);

        $declined = app(PicAuthorizationService::class)->declineFromPayload(
            $this->payload($flight),
            $reviewer,
            'PIC details need correction.',
        );

        $this->assertModelExists($declined);
        $this->assertSame('declined', $declined->pic_authorization_status);
        $this->assertSame($reviewer->id, $declined->pic_authorization_declined_by_user_id);
        $this->assertSame('PIC details need correction.', $declined->pic_authorization_decline_reason);
        $this->assertTrue($declined->requiresPicAuthorization());
    }

    private function user(UserRole $role, ?string $licenseType = null, ?Operator $operator = null): User
    {
        $user = User::factory()->create([
            'role' => $role,
            'is_active' => true,
            'operator_id' => $operator?->id,
        ]);

        if ($licenseType !== null) {
            $user->pilotProfile()->create([
                'license_type' => $licenseType,
                'license_number' => 'LIC-123',
                'ratings' => 'C172',
                'license_expiry_date' => now()->addYear()->toDateString(),
            ]);
        }

        return $user->refresh();
    }

    /** @param array<string, mixed> $attributes */
    private function awaitingFlight(User $authorizer, array $attributes = []): Flight
    {
        $preparer = in_array($authorizer->role, [UserRole::Dispatch, UserRole::OperatorStaff], true)
            ? $authorizer
            : $this->user(UserRole::Dispatch);
        $operator = $authorizer->operator_id !== null
            ? Operator::find($authorizer->operator_id)
            : Operator::factory()->create();

        if ($operator !== null) {
            $authorizer->forceFill(['operator_id' => $operator->id])->save();
        }

        return Flight::create([
            'status' => FlightPlanStatus::AwaitingPic,
            'date_of_flight' => now('Asia/Manila')->addDay()->toDateString(),
            'proposed_time' => '1430',
            'aircraft_identification' => 'PIC-001',
            'departure_aerodrome' => 'RPUS',
            'destination_aerodrome' => 'RPLL',
            'route' => 'DCT',
            'filed_by_user_id' => $authorizer->id,
            'prepared_by_user_id' => $preparer->id,
            'prepared_by_role' => UserRole::Dispatch->value,
            'operator_id' => $operator?->id,
            'pilot_in_command_user_id' => null,
            ...$attributes,
        ])->refresh();
    }

    private function payload(Flight $flight): string
    {
        return app(FlightPlanQrPayloadService::class)->buildPayload($flight);
    }
}
