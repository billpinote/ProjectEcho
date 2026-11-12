<?php

namespace Tests\Feature;

use App\Domain\FlightPlans\Services\FilingAuthorizationService;
use App\Domain\FlightPlans\Services\FlightManifestService;
use App\Domain\FlightPlans\Support\FlightAccess;
use App\Domain\Users\Enums\UserRole;
use App\Filament\Panels\Pilot\Pages\EditFlightManifest;
use App\Filament\Panels\Pilot\Pages\ListFlightManifests;
use App\Models\Flight;
use App\Models\FlightManifest;
use App\Models\Operator;
use App\Models\User;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PilotManifestOwnershipRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_operator_pilots_cannot_list_open_change_or_download_each_others_manifests(): void
    {
        Storage::fake('local');
        $operator = Operator::factory()->create();
        $ethan = User::factory()->create(['role' => UserRole::Pilot, 'operator_id' => $operator->id]);
        $other = User::factory()->create(['role' => UserRole::Pilot, 'operator_id' => $operator->id]);
        $ethansFlight = $this->filing($ethan, 'ETHAN123');
        $othersFlight = $this->filing($other, 'OTHER123');

        foreach ([[$ethan, $ethansFlight, $othersFlight], [$other, $othersFlight, $ethansFlight]] as [$actor, $own, $private]) {
            $package = app(FilingAuthorizationService::class)->request($private);
            $this->actingAs($actor);
            $this->get(ListFlightManifests::getUrl(panel: 'pilot'))->assertOk()
                ->assertSeeText($own->aircraft_identification)->assertDontSeeText($private->aircraft_identification);
            $auditCount = $private->events()->count();
            $this->get(route('flights.view', $private))->assertOk()->assertDontSeeText('Private manifest for '.$private->aircraft_identification);
            $this->assertSame($auditCount, $private->events()->count());
            $this->get(route('flights.view', $own))->assertOk()->assertSeeText('Private manifest for '.$own->aircraft_identification);
            $this->get(EditFlightManifest::getUrl(['flight' => $private], panel: 'pilot'))->assertForbidden();
            $this->get(route('flights.manifest.edit', $private))->assertForbidden();
            $this->put(route('flights.manifest.update', $private), ['remarks' => 'Forbidden'])->assertForbidden();
            $this->get(route('flights.manifest.pdf', ['flight' => $private, 'download' => 1]))->assertForbidden();
            $this->get(route('filing.document', ['package' => $package, 'type' => 'passenger_cargo_manifest']))->assertForbidden();
            $this->assertFalse($actor->can('delete', $private->manifest));
            $this->assertSame('Private manifest for '.$private->aircraft_identification, $private->manifest->fresh()->remarks);

            Filament::setCurrentPanel('pilot');
            Livewire::actingAs($actor)->test(EditFlightManifest::class, ['flight' => $private])->assertForbidden();
        }
    }

    public function test_pilot_flight_involvement_does_not_replace_manifest_creator_identity(): void
    {
        $pilot = User::factory()->create(['role' => UserRole::Pilot]);
        $flight = Flight::factory()->create(['filed_by_user_id' => $pilot->id, 'prepared_by_user_id' => $pilot->id]);
        $manifest = FlightManifest::factory()->create(['flight_id' => $flight->id]);
        $this->assertFalse($pilot->can('view', $manifest));
        $this->assertFalse($pilot->can('update', $manifest));
        $this->assertFalse(FlightAccess::restrictQueryToVisibleManifestFlights(Flight::query(), $pilot)->whereKey($flight)->exists());

        $owner = User::factory()->create(['role' => UserRole::Pilot, 'operator_id' => Operator::factory()]);
        $private = $this->filing($owner, 'PRIVATE123');
        $pilot->update(['operator_id' => $owner->operator_id]);
        $private->update(['pilot_in_command_user_id' => $pilot->id]);
        $this->assertTrue($pilot->can('view', $private));
        $this->assertFalse($pilot->can('view', $private->manifest));
        $this->assertFalse($pilot->can('update', $private->manifest));
        $this->assertFalse($pilot->can('create', [FlightManifest::class, $private]));
    }

    public function test_unsaved_manifest_selector_lists_the_pilots_prepared_and_legacy_filed_flights(): void
    {
        $pilot = User::factory()->create(['role' => UserRole::Pilot, 'operator_id' => Operator::factory()]);
        $other = User::factory()->create(['role' => UserRole::Pilot, 'operator_id' => $pilot->operator_id]);
        $prepared = Flight::factory()->create(['filed_by_user_id' => $other->id, 'prepared_by_user_id' => $pilot->id, 'operator_id' => $pilot->operator_id]);
        $legacy = Flight::factory()->create(['filed_by_user_id' => $pilot->id, 'operator_id' => $pilot->operator_id]);
        Flight::factory()->create(['filed_by_user_id' => $other->id, 'prepared_by_user_id' => $other->id, 'operator_id' => $pilot->operator_id]);
        $this->assertEqualsCanonicalizing([$prepared->id, $legacy->id],
            FlightAccess::restrictQueryToVisibleManifestFlights(Flight::query(), $pilot)->pluck('id')->all());
        $this->assertTrue($pilot->can('create', [FlightManifest::class, $prepared]));
        $this->assertTrue($pilot->can('create', [FlightManifest::class, $legacy]));
    }

    public function test_dispatch_keeps_operator_visibility_and_its_existing_creator_edit_restriction(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Pilot, 'operator_id' => Operator::factory()]);
        $flight = $this->filing($owner, 'LOCAL123');
        $outsideOwner = User::factory()->create(['role' => UserRole::Pilot, 'operator_id' => Operator::factory()]);
        $outside = $this->filing($outsideOwner, 'OUTSIDE123');
        $dispatch = User::factory()->create(['role' => UserRole::Dispatch, 'operator_id' => $owner->operator_id]);
        $this->actingAs($dispatch);
        $this->get(\App\Filament\Panels\Dispatch\Pages\ListFlightManifests::getUrl(panel: 'dispatch'))->assertOk()
            ->assertSeeText('LOCAL123')->assertDontSeeText('OUTSIDE123');
        $this->get(route('flights.manifest.edit', $flight))->assertRedirect();
        $this->get(route('flights.manifest.pdf', $flight))->assertOk();
        $this->put(route('flights.manifest.update', $flight), ['remarks' => 'Forbidden'])->assertForbidden();
        $this->get(route('flights.manifest.edit', $outside))->assertForbidden();
        $this->get(route('flights.manifest.pdf', $outside))->assertForbidden();
    }

    public function test_manifest_listing_has_seven_columns_with_a_separate_action_and_optional_placeholders(): void
    {
        $pilot = User::factory()->create(['role' => UserRole::Pilot, 'first_name' => 'Ethan', 'last_name' => 'Villanueva', 'middle_name' => null, 'suffix' => null]);
        $flight = $this->filing($pilot, 'COLUMNS123');
        $flight->update(['prepared_by_name' => 'DIFFERENT FLIGHT PREPARER', 'pilot_in_command' => 'CERTIFIED PIC', 'proposed_time' => '0035']);
        $response = $this->actingAs($pilot)->get(ListFlightManifests::getUrl(panel: 'pilot'))->assertOk()
            ->assertSeeInOrder(['Callsign', 'DOF', 'PTD', 'PIC', 'Prepared By', 'Status', 'Actions'])
            ->assertSeeText('CERTIFIED PIC')->assertSeeText('ETHAN VILLANUEVA')
            ->assertSeeText('0035Z')->assertSeeText(Carbon::parse($flight->date_of_flight)->format('d M Y'))
            ->assertDontSeeText('Flight #');

        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $cells = $xpath->query('//table[contains(@class, "fi-ta-table")]/tbody/tr[1]/td');
        $this->assertCount(7, $cells);
        $this->assertSame('COLUMNS123', trim($cells->item(0)->textContent));
        $this->assertStringContainsString('Actions', $cells->item(6)->textContent);
        $this->assertStringContainsString('Edit manifest', $cells->item(6)->textContent);
        $response->assertSee('echo-flight-row-actions-trigger', false)
            ->assertSee('echo-flight-status-cell', false)->assertSee('fi-badge', false);
        $this->assertSame(route('flights.manifest.edit', $flight), $cells->item(6)->getElementsByTagName('a')->item(0)->getAttribute('href'));

        Filament::setCurrentPanel('pilot');
        Livewire::actingAs($pilot)->test(ListFlightManifests::class)
            ->assertCanSeeTableRecords([$flight])
            ->assertCanRenderTableColumn('aircraft_identification')
            ->assertCanRenderTableColumn('status_presentation')
            ->assertTableColumnFormattedStateSet('proposed_time', '0035Z', $flight)
            ->assertTableColumnFormattedStateSet('manifest_prepared_by', 'ETHAN VILLANUEVA', $flight)
            ->assertTableActionVisible('manifest', $flight)
            ->assertTableActionHasLabel('manifest', 'Edit manifest', $flight)
            ->assertTableActionHasUrl('manifest', route('flights.manifest.edit', $flight), $flight);

        Flight::factory()->create(['pilot_in_command' => null, 'prepared_by_name' => null, 'prepared_by_user_id' => null, 'filed_by_user_id' => null]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $response = $this->actingAs($admin)->get(route('manifests.index'))->assertOk();
        $this->assertSame(2, substr_count($response->getContent(), '>—</td>'));
    }

    private function filing(User $creator, string $callsign): Flight
    {
        $flight = Flight::factory()->create([
            'filed_by_user_id' => $creator->id, 'prepared_by_user_id' => $creator->id,
            'operator_id' => $creator->operator_id, 'aircraft_identification' => $callsign,
        ]);
        app(FlightManifestService::class)->save($flight, $creator, ['remarks' => 'Private manifest for '.$callsign]);

        return $flight->fresh();
    }
}
