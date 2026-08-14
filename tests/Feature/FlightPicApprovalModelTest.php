<?php

namespace Tests\Feature;

use App\Domain\FlightPlans\Enums\FlightPlanStatus;
use App\Domain\Users\Enums\UserRole;
use App\Models\Flight;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlightPicApprovalModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_self_prepared_self_pic_flight_does_not_require_separate_authorization(): void
    {
        $pilot = $this->user(UserRole::Pilot);
        $flight = $this->flight([
            'prepared_by_user_id' => $pilot->id,
            'pilot_in_command_user_id' => $pilot->id,
        ]);

        $this->assertFalse($flight->requiresPicAuthorization());
        $this->assertTrue($flight->canSubmitToAtc());
    }

    public function test_spl_or_other_user_preparing_for_another_pic_requires_authorization(): void
    {
        $spl = $this->user(UserRole::Pilot);
        $pic = $this->user(UserRole::Pilot);
        $flight = $this->flight([
            'prepared_by_user_id' => $spl->id,
            'prepared_by_role' => UserRole::Pilot->value,
            'pilot_in_command_user_id' => $pic->id,
        ]);

        $this->assertTrue($flight->requiresPicAuthorization());
        $this->assertFalse($flight->canSubmitToAtc());
    }

    public function test_pic_can_be_null_while_awaiting_identification(): void
    {
        $dispatch = $this->user(UserRole::Dispatch);
        $flight = $this->flight([
            'status' => FlightPlanStatus::AwaitingPic,
            'prepared_by_user_id' => $dispatch->id,
            'pilot_in_command_user_id' => null,
        ]);

        $this->assertTrue($flight->requiresPicAuthorization());
        $this->assertFalse($flight->canSubmitToAtc());
    }

    public function test_current_pic_authorization_allows_submission_to_atc(): void
    {
        $dispatch = $this->user(UserRole::Dispatch);
        $pic = $this->user(UserRole::Pilot);
        $flight = $this->flight([
            'prepared_by_user_id' => $dispatch->id,
            'pilot_in_command_user_id' => $pic->id,
            'pic_authorized_by_user_id' => $pic->id,
            'pic_authorized_at' => now(),
            'pic_authorization_method' => 'echo_account',
            'revision_number' => 3,
            'pic_authorized_revision' => 3,
        ]);

        $this->assertTrue($flight->isPicAuthorized());
        $this->assertTrue($flight->isPicAuthorizationCurrent());
        $this->assertTrue($flight->canSubmitToAtc());
    }

    public function test_revision_mismatch_makes_pic_authorization_stale(): void
    {
        $dispatch = $this->user(UserRole::Dispatch);
        $pic = $this->user(UserRole::Pilot);
        $flight = $this->flight([
            'prepared_by_user_id' => $dispatch->id,
            'pilot_in_command_user_id' => $pic->id,
            'pic_authorized_by_user_id' => $pic->id,
            'pic_authorized_at' => now(),
            'revision_number' => 4,
            'pic_authorized_revision' => 3,
        ]);

        $this->assertTrue($flight->isPicAuthorized());
        $this->assertFalse($flight->isPicAuthorizationCurrent());
        $this->assertFalse($flight->canSubmitToAtc());
    }

    public function test_invalidating_authorization_clears_authorization_without_destroying_snapshots(): void
    {
        $dispatch = $this->user(UserRole::Dispatch);
        $pic = $this->user(UserRole::Pilot);
        $flight = $this->flight([
            'prepared_by_user_id' => $dispatch->id,
            'prepared_by_name' => 'Dispatcher Person',
            'prepared_by_role' => UserRole::Dispatch->value,
            'pilot_in_command_user_id' => $pic->id,
            'pilot_in_command' => 'PIC Snapshot',
            'pic_authorized_by_user_id' => $pic->id,
            'pic_authorized_at' => now(),
            'pic_authorization_method' => 'echo_account',
            'pic_authorization_token' => 'token-value',
            'pic_authorization_token_expires_at' => now()->addMinutes(10),
            'revision_number' => 2,
            'pic_authorized_revision' => 2,
        ]);

        $flight->invalidatePicAuthorization();
        $flight->refresh();

        $this->assertNull($flight->pic_authorized_by_user_id);
        $this->assertNull($flight->pic_authorized_at);
        $this->assertNull($flight->pic_authorization_method);
        $this->assertNull($flight->pic_authorization_token);
        $this->assertNull($flight->pic_authorization_token_expires_at);
        $this->assertNull($flight->pic_authorized_revision);
        $this->assertSame($dispatch->id, $flight->prepared_by_user_id);
        $this->assertSame('Dispatcher Person', $flight->prepared_by_name);
        $this->assertSame($pic->id, $flight->pilot_in_command_user_id);
        $this->assertSame('PIC Snapshot', $flight->pilot_in_command);
        $this->assertSame(2, $flight->revision_number);
    }

    public function test_revision_increment_is_distinct_from_authorization_invalidation(): void
    {
        $flight = $this->flight(['revision_number' => 1]);

        $flight->incrementRevisionNumber();

        $this->assertSame(2, $flight->refresh()->revision_number);
    }

    public function test_existing_legacy_flight_records_remain_usable(): void
    {
        $flight = $this->flight([
            'prepared_by_user_id' => null,
            'pilot_in_command_user_id' => null,
            'pilot_in_command' => 'LEGACY PIC',
            'status' => FlightPlanStatus::Pending,
        ]);

        $this->assertFalse($flight->requiresPicAuthorization());
        $this->assertTrue($flight->canSubmitToAtc());
        $this->assertSame('LEGACY PIC', $flight->pilot_in_command);
    }

    public function test_awaiting_pic_is_separate_from_pending_atc_status(): void
    {
        $awaitingPic = $this->flight(['status' => FlightPlanStatus::AwaitingPic]);
        $pendingAtc = $this->flight(['status' => FlightPlanStatus::Pending]);

        $this->assertSame('awaiting_pic', FlightPlanStatus::AwaitingPic->value);
        $this->assertNotSame($pendingAtc->status, $awaitingPic->status);
        $this->assertSame('pending', FlightPlanStatus::Pending->value);
    }

    public function test_new_relationships_resolve_to_users(): void
    {
        $preparer = $this->user(UserRole::Dispatch);
        $pic = $this->user(UserRole::Pilot);
        $authorizer = $this->user(UserRole::Pilot);
        $flight = $this->flight([
            'prepared_by_user_id' => $preparer->id,
            'pilot_in_command_user_id' => $pic->id,
            'pic_authorized_by_user_id' => $authorizer->id,
            'pic_authorized_at' => now(),
        ]);

        $this->assertTrue($flight->preparedBy->is($preparer));
        $this->assertTrue($flight->pilotInCommandUser->is($pic));
        $this->assertTrue($flight->picAuthorizedBy->is($authorizer));
        $this->assertTrue($preparer->preparedFlights()->whereKey($flight)->exists());
        $this->assertTrue($pic->flightsAsPic()->whereKey($flight)->exists());
        $this->assertTrue($authorizer->picAuthorizations()->whereKey($flight)->exists());
    }

    private function user(UserRole $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function flight(array $attributes = []): Flight
    {
        return Flight::create([
            'status' => FlightPlanStatus::Pending,
            'date_of_flight' => now('Asia/Manila')->addDay()->toDateString(),
            'proposed_time' => '1430',
            'aircraft_identification' => 'PIC001',
            'departure_aerodrome' => 'RPUS',
            'destination_aerodrome' => 'RPLL',
            'route' => 'DCT',
            ...$attributes,
        ]);
    }
}
