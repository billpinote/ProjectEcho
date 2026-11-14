<?php

namespace Tests\Feature;

use App\Domain\FlightPlans\Enums\FlightPlanStatus;
use App\Domain\FlightPlans\Services\FilingAuthorizationService;
use App\Domain\FlightPlans\Services\FlightPlanMutationService;
use App\Domain\Users\Enums\UserRole;
use App\Filament\Panels\Dispatch\Pages\ListFlightManifests;
use App\Models\Flight;
use App\Models\FlightPlanEvent;
use App\Models\Operator;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SubmittedFlightPlanProtectionTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('roles')]
    public function test_no_role_can_edit_submitted_contents(UserRole $role): void
    {
        $actor = User::factory()->create(['role' => $role, 'operator_id' => Operator::factory(), 'station' => 'RPUS']);
        $this->actingAs($actor);
        foreach (FlightPlanStatus::cases() as $status) {
            $flight = Flight::factory()->create([
                'status' => $status, 'operator_id' => $actor->operator_id,
                'filed_by_user_id' => $actor->id, 'prepared_by_user_id' => $actor->id,
                'pilot_in_command_user_id' => $actor->id,
            ]);
            $this->assertFalse($actor->can('update', $flight));
            foreach ([false, true] as $quiet) {
                try {
                    $flight->route = 'ALTERED';
                    $quiet ? $flight->saveQuietly() : $flight->save();
                    $this->fail('Submitted contents were edited.');
                } catch (ValidationException $exception) {
                    $this->assertArrayHasKey('flight', $exception->errors());
                }
                $this->assertSame('DCT', $flight->refresh()->route);
            }
        }
    }

    public static function roles(): array
    {
        return array_map(fn (UserRole $role): array => [$role], UserRole::cases());
    }

    public function test_direct_edit_urls_reject_even_privileged_users(): void
    {
        foreach (UserRole::cases() as $role) {
            $panel = $role === UserRole::Pilot ? 'pilot' : 'dispatch';
            $actor = User::factory()->create(['role' => $role, 'operator_id' => Operator::factory(), 'station' => 'RPUS']);
            $flight = Flight::factory()->create([
                'operator_id' => $actor->operator_id, 'filed_by_user_id' => $actor->id,
                'prepared_by_user_id' => $actor->id, 'pilot_in_command_user_id' => $actor->id,
                'status' => FlightPlanStatus::AwaitingPic,
            ]);
            $this->actingAs($actor)->get("/{$panel}/flights/{$flight->id}/edit")->assertForbidden();
        }
    }

    public function test_dispatch_manifest_rows_use_authorized_manifest_destination(): void
    {
        $operator = Operator::factory()->create();
        $dispatch = User::factory()->create(['role' => UserRole::Dispatch, 'operator_id' => $operator->id]);
        $own = Flight::factory()->create(['operator_id' => $operator->id, 'filed_by_user_id' => $dispatch->id]);
        $other = Flight::factory()->create(['operator_id' => Operator::factory()]);
        Filament::setCurrentPanel('dispatch');
        $page = Livewire::actingAs($dispatch)->test(ListFlightManifests::class)
            ->assertCanSeeTableRecords([$own])->assertCanNotSeeTableRecords([$other]);
        $url = route('flights.manifest.edit', $own);
        $this->assertSame($url, $page->instance()->getTable()->getRecordUrl($own));
        $page->assertSee('href="'.$url.'"', false)->assertSee('Manifest actions');
        $this->get($url)->assertRedirect();
        $this->get(route('flights.manifest.edit', $other))->assertForbidden();
        $this->put(route('flights.manifest.update', $other), [])->assertForbidden();
    }

    public function test_session_drafts_remain_editable_without_persisting_a_filing(): void
    {
        $this->withSession(['flight_plan_preview' => ['aircraft_identification' => 'RPCDRAFT', 'route' => 'DCT']])
            ->post(route('flightplan.edit-preview'))->assertRedirect(route('flightplan'))
            ->assertSessionHas('_old_input.aircraft_identification', 'RPCDRAFT');
        $this->assertDatabaseCount('flights', 0);
    }

    public function test_authorization_context_cannot_change_filed_route(): void
    {
        $flight = Flight::factory()->create();
        try {
            app(FilingAuthorizationService::class)->recordingDecision($flight,
                fn () => $flight->forceFill(['route' => 'ALTERED'])->saveQuietly());
            $this->fail('PIC authorization permitted a route change.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('flight', $exception->errors());
        }
        $this->assertSame('DCT', $flight->refresh()->route);
    }

    public function test_equivalent_time_format_does_not_change_submitted_contents(): void
    {
        $flight = Flight::factory()->create(['proposed_time' => '1430']);
        $flight->proposed_time = '14:30:00';
        $flight->save();
        $this->assertSame('14:30', $flight->refresh()->proposed_time);
    }

    public function test_cancellation_keeps_filing_contents_and_audit_history(): void
    {
        $pic = User::factory()->create(['role' => UserRole::Pilot]);
        $flight = Flight::factory()->create(['filed_by_user_id' => $pic->id, 'pilot_in_command_user_id' => $pic->id]);
        $service = app(FlightPlanMutationService::class);
        $service->recordSubmission($flight, $pic);
        $service->cancel($flight, $pic, 'Replacement required');
        $flight->refresh();
        $this->assertSame(FlightPlanStatus::Cancelled, $flight->status);
        $this->assertSame('DCT', $flight->route);
        $this->assertTrue(Flight::query()->archivedForPilot()->whereKey($flight)->exists());
        $this->assertTrue($flight->events()->where('event_type', FlightPlanEvent::TYPE_CREATED)->exists());
        $this->assertTrue($flight->events()->where('event_type', FlightPlanEvent::TYPE_CANCELLED)->exists());
        $this->assertTrue($pic->can('view', $flight));
        $this->assertFalse($pic->can('cancel', $flight));
        $replacement = Flight::factory()->create(['filed_by_user_id' => $pic->id, 'pilot_in_command_user_id' => $pic->id]);
        $this->assertNotSame($flight->id, $replacement->id);
        $this->assertModelExists($flight);
    }
}
