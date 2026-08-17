<?php

namespace Tests\Feature;

use App\Domain\FlightPlans\Enums\FlightPlanStatus;
use App\Domain\FlightPlans\Services\FlightPlanQrPayloadService;
use App\Domain\FlightPlans\Services\PicAuthorizationService;
use App\Domain\Pilots\Enums\PilotLicenseType;
use App\Domain\Users\Enums\UserRole;
use App\Filament\Shared\Resources\Flights\FlightResource;
use App\Models\Flight;
use App\Models\Operator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

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

        $this->expectException(ValidationException::class);
        app(PicAuthorizationService::class)->authorizeFromPayload($this->payload($flight), $preparer);
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
        $reviewer = $this->user(UserRole::Dispatch);
        $flight = $this->awaitingFlight($reviewer);

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

    private function user(UserRole $role, ?string $licenseType = null): User
    {
        $user = User::factory()->create(['role' => $role, 'is_active' => true]);

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
        $operator = $authorizer->isDispatch() ? Operator::factory()->create() : null;

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
