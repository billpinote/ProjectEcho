<?php

namespace Tests\Feature;

use App\Domain\FlightPlans\Enums\FlightPlanStatus;
use App\Domain\FlightPlans\Services\FilingAuthorizationService;
use App\Domain\FlightPlans\Services\FlightPlanQrPayloadService;
use App\Domain\Users\Enums\UserRole;
use App\Filament\Panels\Atmo\Resources\AcceptedFlights\Pages\ListAcceptedFlights;
use App\Filament\Panels\Atmo\Resources\AllFlightPlans\Pages\EditAllFlight;
use App\Models\Flight;
use App\Models\FlightManifest;
use App\Models\Operator;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EarlyFlightPlanPreviewAccessTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('previewRoles')]
    public function test_authorized_users_can_preview_but_cannot_operate_unsigned_flights(UserRole $role, FlightPlanStatus $status): void
    {
        $operator = Operator::factory()->create();
        $flight = Flight::factory()->create(['operator_id' => $operator->id, 'status' => $status]);
        $actor = User::factory()->create(['role' => $role, 'station' => 'RPUS',
            'operator_id' => $role === UserRole::Atmo ? Operator::factory()->create()->id : $operator->id]);

        $this->actingAs($actor)->get(route('flights.view', $flight))->assertOk()
            ->assertViewIs('flightplan.pdf')->assertViewHas('canAcceptFlightPlan', false)
            ->assertViewHas('canRejectFlightPlan', false)->assertSeeText('Manifest Required')->assertSeeText('Close Preview')
            ->assertDontSee('data-open-review-modal="accept-flight-plan"', false)
            ->assertDontSee('data-open-review-modal="reject-flight-plan"', false);
        $this->assertTrue(Flight::query()->visibleTo($actor)->whereKey($flight)->exists());
        $this->assertNull($flight->fresh()->reviewed_at);
        foreach (['accept', 'reject', 'update', 'updateStartUpTime', 'updateBlockOffTime', 'updateAirborneTime', 'updateTouchdownTime', 'updateShutdownTime'] as $ability) {
            $this->assertFalse($actor->can($ability, $flight), $ability);
        }
        $this->post(route('flights.accept', $flight))->assertForbidden();
        $this->post(route('flights.reject', $flight), ['rejection_reason' => 'Forged'])->assertForbidden();
        if (! in_array($role, [UserRole::Avsec, UserRole::Dispatch], true)) {
            $this->assertFalse($actor->can('create', [FlightManifest::class, $flight]));
            $this->put(route('flights.manifest.update', $flight), ['remarks' => 'Forged'])->assertForbidden();
        }
        $this->assertSame($status, $flight->fresh()->status);
    }

    public static function previewRoles(): array
    {
        return collect([UserRole::Atmo, UserRole::Pilot, UserRole::OperatorStaff, UserRole::Avsec, UserRole::Dispatch])
            ->crossJoin([FlightPlanStatus::AwaitingPic, FlightPlanStatus::Pending])->all();
    }

    public function test_forged_livewire_timestamp_and_edit_actions_cannot_mutate_unsigned_flight(): void
    {
        $flight = Flight::factory()->create(['status' => FlightPlanStatus::AwaitingPic]);
        $actor = User::factory()->create(['role' => UserRole::Atmo, 'station' => 'RPUS']);
        Filament::setCurrentPanel(Filament::getPanel('atmo'));
        Livewire::actingAs($actor)->test(ListAcceptedFlights::class)
            ->call('confirmStartUpNow', $flight->id)->assertForbidden();
        Livewire::actingAs($actor)->test(ListAcceptedFlights::class)
            ->call('confirmBlockOffNow', $flight->id)->assertForbidden();
        Livewire::actingAs($actor)->test(EditAllFlight::class, ['record' => $flight->id])->assertForbidden();
        $this->assertNull($flight->fresh()->time_start_up);
        $this->assertNull($flight->fresh()->time_block_off);
    }

    public function test_other_operator_cannot_use_preview_assets_qr_lookup_or_stale_session(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $flight = Flight::factory()->create(['operator_id' => Operator::factory()->create()->id, 'status' => FlightPlanStatus::AwaitingPic]);
        FlightManifest::factory()->for($flight)->create();
        $actor = User::factory()->create(['role' => UserRole::Dispatch, 'operator_id' => Operator::factory()->create()->id]);
        $this->actingAs($actor)->withSession(['public_flight_access' => [$flight->id],
            'scanned_flight_plan_previews.stale' => ['flight_id' => $flight->id, 'snapshot' => $flight->toArray()]]);
        foreach (['flights.view', 'flights.pdf.download', 'flights.qr', 'flights.qr.download', 'flights.manifest.pdf'] as $route) {
            $this->get(route($route, $flight))->assertForbidden();
        }
        $payload = app(FlightPlanQrPayloadService::class)->buildPayload($flight);
        $this->post(route('flightplan.scan-qr.lookup'), ['payload' => $payload])->assertForbidden();
        $this->get(route('flightplan.scan-qr.preview', ['token' => 'stale']))->assertForbidden();
        $this->post(route('flightplan.edit-from-qr'), ['payload' => $payload])->assertForbidden();
    }

    public function test_pending_package_documents_are_readable_without_pic_mutation_authority(): void
    {
        Storage::fake('local');
        $flight = Flight::factory()->create(['status' => FlightPlanStatus::AwaitingPic]);
        $package = app(FilingAuthorizationService::class)->request($flight);
        $atmo = User::factory()->create(['role' => UserRole::Atmo, 'station' => 'RPUS']);
        $this->actingAs($atmo)->get(route('filing.document', ['package' => $package, 'type' => 'flight_plan']))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('flights.pdf.download', $flight))->assertOk();
        $this->get(route('flights.qr', $flight))->assertOk();
        $this->assertFalse($atmo->can('accept', $flight));
        $unrelated = User::factory()->create(['role' => UserRole::Dispatch, 'operator_id' => Operator::factory()->create()->id]);
        $this->actingAs($unrelated)->get(route('filing.document', ['package' => $package, 'type' => 'flight_plan']))->assertForbidden();
    }

    public function test_guest_offline_preview_uses_only_the_verified_snapshot(): void
    {
        $flight = Flight::factory()->create(['aircraft_identification' => 'SERVER-ONLY']);
        $snapshot = $flight->toArray();
        $snapshot['aircraft_identification'] = 'SIGNED-SNAPSHOT';
        $this->withSession(['scanned_flight_plan_previews.offline' => ['flight_id' => $flight->id, 'snapshot' => $snapshot]])
            ->get(route('flightplan.scan-qr.preview', ['token' => 'offline']))->assertOk()
            ->assertSeeText('SIGNED-SNAPSHOT')->assertDontSeeText('SERVER-ONLY');
    }

    public function test_new_operator_previews_do_not_expand_archive_access(): void
    {
        $operator = Operator::factory()->create();
        $pilot = User::factory()->create(['role' => UserRole::Pilot, 'operator_id' => $operator->id]);
        foreach ([['pic_authorization_status' => 'declined'], ['pic_authorization_archived_at' => now()], ['status' => FlightPlanStatus::Completed]] as $attributes) {
            $flight = Flight::factory()->create(['operator_id' => $operator->id, 'status' => FlightPlanStatus::AwaitingPic, ...$attributes]);
            $this->actingAs($pilot)->get(route('flights.view', $flight))->assertForbidden();
            $this->assertFalse(Flight::query()->visibleTo($pilot)->whereKey($flight)->exists());
        }
    }

    public function test_inactive_users_and_other_stations_remain_denied(): void
    {
        $flight = Flight::factory()->create(['status' => FlightPlanStatus::AwaitingPic]);
        foreach ([['station' => 'RPLL'], ['station' => 'RPUS', 'is_active' => false]] as $attributes) {
            $actor = User::factory()->create(['role' => UserRole::Atmo, ...$attributes]);
            $this->actingAs($actor)->get(route('flights.view', $flight))->assertForbidden();
            $this->post(route('flights.accept', $flight))->assertForbidden();
        }
        $atmo = User::factory()->create(['role' => UserRole::Atmo, 'station' => 'RPUS']);
        $flight->update(['departure_aerodrome' => 'RPLL']);
        $this->actingAs($atmo)->get(route('flights.view', $flight))->assertForbidden();
    }
}
