<?php

namespace Tests\Feature;

use App\Domain\FlightPlans\Services\FlightManifestService;
use App\Domain\FlightPlans\Services\FlightPlanMutationService;
use App\Models\Flight;
use App\Models\FlightPlanEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilingActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_changes_record_actor_and_only_changed_values(): void
    {
        $flight = Flight::factory()->create(['route' => 'OLD ROUTE']);
        $this->actingAs($flight->filedBy);
        app(FlightPlanMutationService::class)->recordSubmission($flight, $flight->filedBy);
        app(FlightPlanMutationService::class)->recordSubmission($flight, $flight->filedBy);
        $this->assertSame(1, $flight->events()->where('event_type', FlightPlanEvent::TYPE_CREATED)->count());
        $flight->update(['route' => 'NEW ROUTE']);
        $flight->update(['route' => 'NEW ROUTE']);
        $event = $flight->events()->where('event_type', FlightPlanEvent::TYPE_UPDATED)->sole();
        $this->assertSame(['route' => 'OLD ROUTE'], $event->old_values);
        $this->assertSame('NEW ROUTE', $event->new_values['route']);
        $this->assertSame($flight->filedBy->id, $event->actor_user_id);
        $this->assertNotNull($event->created_at);
        $this->get(route('flights.view', $flight))->assertOk()->assertSee('Flight plan updated')->assertSee('OLD ROUTE')->assertSee('NEW ROUTE');
    }

    public function test_manifest_history_ignores_recreated_person_ids_and_normalizes_values(): void
    {
        $flight = Flight::factory()->create();
        $service = app(FlightManifestService::class);
        $manifest = $service->save($flight, $flight->filedBy, ['total_cargo_weight' => 1]);
        $values = ['persons' => $manifest->persons->map->only(['full_name', 'nationality', 'classification'])->all(), 'total_cargo_weight' => '1.000'];
        $service->save($flight, $flight->filedBy, $values);
        $service->save($flight, $flight->filedBy, [...$values, 'remarks' => 'New cargo']);
        $this->assertSame(1, $flight->events()->where('event_type', FlightPlanEvent::TYPE_MANIFEST_CREATED)->count());
        $event = $flight->events()->where('event_type', FlightPlanEvent::TYPE_MANIFEST_UPDATED)->sole();
        $this->assertSame(['remarks' => null], $event->old_values);
        $this->assertSame('New cargo', $event->new_values['remarks']);
        $this->assertSame($flight->filedBy->id, $event->actor_user_id);
        $this->actingAs($flight->filedBy)->get(route('flights.view', $flight))->assertOk()->assertSee('Manifest created')->assertSee('Manifest updated')->assertSee('New cargo');
        $service->save($flight, $flight->filedBy, ['remarks' => null]);
        $cleared = $flight->events()->where('event_type', FlightPlanEvent::TYPE_MANIFEST_UPDATED)->latest('id')->firstOrFail();
        $this->assertSame('New cargo', $cleared->old_values['remarks']);
        $this->assertNull($cleared->new_values['remarks']);
        $service->delete($flight, $flight->filedBy);
        $this->assertSame(1, $flight->events()->where('event_type', FlightPlanEvent::TYPE_MANIFEST_DELETED)->count());
        $this->get(route('flights.view', $flight))->assertOk()->assertSee('Manifest deleted');
    }
}
