<?php

namespace Tests\Feature;

use App\Domain\FlightPlans\Enums\FlightPlanStatus;
use App\Domain\FlightPlans\Services\FlightManifestService;
use App\Domain\FlightPlans\Services\FlightPlanMutationService;
use App\Domain\Users\Enums\UserRole;
use App\Filament\Panels\Pilot\Pages\EditFlightManifest;
use App\Filament\Panels\Pilot\Pages\ListFlightManifests;
use App\Models\Flight;
use App\Models\Operator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlightManifestPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_menu_opens_guest_sign_in_prompt_without_persisting_data(): void
    {
        $this->get(route('flightplan'))->assertOk()->assertSee(route('manifests.index'), false);
        $this->get(route('manifests.index'))->assertOk()->assertSeeText('Sign in to prepare a manifest');
        $flight = Flight::factory()->create();
        $this->get(EditFlightManifest::getUrl(['flight' => $flight], panel: 'pilot'))->assertRedirect(route('filament.pilot.auth.login'));
        $this->get(route('flights.manifest.edit', $flight))->assertRedirect(route('gateway'));
        $this->put(route('flights.manifest.update', $flight), [])->assertRedirect(route('gateway'));
        $this->assertDatabaseCount('flight_manifests', 0);
    }

    public function test_selector_lists_only_the_pilots_visible_flights(): void
    {
        $flight = Flight::factory()->create(['aircraft_identification' => 'RPCOWN']);
        Flight::factory()->create(['aircraft_identification' => 'RPCOTHER']);
        $this->actingAs($flight->filedBy)->get(ListFlightManifests::getUrl(panel: 'pilot'))->assertOk()->assertSee('fi-sidebar', false)
            ->assertSeeText('RPCOWN')->assertDontSeeText('RPCOTHER')->assertSeeText('Prepare manifest');
        $this->get(route('manifests.index'))->assertRedirect(ListFlightManifests::getUrl(panel: 'pilot'));
        $response = $this->get('/pilot');
        $response->assertSeeInOrder(['Dashboard', 'Create Flight Plan', 'Flight Manifests']);
    }

    public function test_preparation_prefills_flight_and_pic_without_creating_a_draft_on_get(): void
    {
        $flight = Flight::factory()->create(['other_info_expiry_date_to_operate' => '2027-12-31']);
        $this->actingAs($flight->filedBy)->get(EditFlightManifest::getUrl(['flight' => $flight], panel: 'pilot'))->assertOk()
            ->assertSeeText($flight->pilot_in_command)->assertSee('2027-12-31')->assertSeeText('Save manifest')
            ->assertSee('data-person-add', false)->assertSeeText('PIC license number')
            ->assertSee('fi-sidebar', false)->assertSeeText('Create Flight Plan')->assertSeeText('My Flight Plans')
            ->assertSee(route('filament.pilot.resources.flights.create'), false);
        $this->assertDatabaseCount('flight_manifests', 0);
    }

    public function test_manifest_form_creates_and_updates_one_linked_manifest(): void
    {
        $flight = Flight::factory()->create();
        $this->actingAs($flight->filedBy)->put(route('flights.manifest.update', $flight), $this->completeData($flight))
            ->assertRedirect(EditFlightManifest::getUrl(['flight' => $flight], panel: 'pilot'))->assertSessionHasNoErrors();
        $manifest = $flight->manifest;
        $this->assertCount(1, $manifest->persons);
        $this->assertSame($flight->filed_by_user_id, $manifest->created_by_user_id);
        $this->get(EditFlightManifest::getUrl(['flight' => $flight], panel: 'pilot'))->assertOk()->assertSeeText('Saved manifest: ready');
        $this->put(route('flights.manifest.update', $flight), ['remarks' => 'Updated', 'persons' => [
            ['full_name' => $flight->pilot_in_command, 'nationality' => 'Filipino', 'classification' => 'pic'],
            ['full_name' => 'Second Person', 'nationality' => 'Filipino', 'classification' => 'crew'],
        ]])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('flight_manifests', 1);
        $this->assertSame('Updated', $manifest->fresh()->remarks);
        $this->assertSame(['pic', 'crew'], $manifest->fresh()->persons->pluck('classification')->all());
        $this->assertSame(1, $flight->fresh()->persons_on_board);
        $this->get(EditFlightManifest::getUrl(['flight' => $flight], panel: 'pilot'))->assertSeeText('person count must match')->assertSeeText('Saved manifest: incomplete');
    }

    public function test_invalid_fields_restore_input_and_do_not_save(): void
    {
        $flight = Flight::factory()->create();
        $this->actingAs($flight->filedBy)->from(EditFlightManifest::getUrl(['flight' => $flight], panel: 'pilot'))
            ->put(route('flights.manifest.update', $flight), ['aircraft_owner_name' => 'Keep my input', 'total_cargo_weight' => -1])
            ->assertSessionHasErrors('total_cargo_weight')->assertRedirect(EditFlightManifest::getUrl(['flight' => $flight], panel: 'pilot'));
        $this->get(EditFlightManifest::getUrl(['flight' => $flight], panel: 'pilot'))->assertSee('Keep my input')->assertSeeText('Your changes were not saved');
        $this->assertDatabaseCount('flight_manifests', 0);
    }

    public function test_removing_all_people_clears_saved_people_instead_of_preserving_them(): void
    {
        $flight = Flight::factory()->create();
        app(FlightManifestService::class)->save($flight, $flight->filedBy, $this->completeData($flight));
        $this->actingAs($flight->filedBy)->put(route('flights.manifest.update', $flight), ['remarks' => 'No people yet'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('manifest_persons', 0);
        $this->get(EditFlightManifest::getUrl(['flight' => $flight], panel: 'pilot'))->assertSeeText('Saved manifest: incomplete');
    }

    public function test_accepted_manifest_is_viewable_but_direct_writes_are_denied(): void
    {
        $flight = Flight::factory()->create();
        app(FlightManifestService::class)->save($flight, $flight->filedBy, $this->completeData($flight));
        $atmo = User::factory()->create(['role' => UserRole::Atmo, 'station' => 'RPUS']);
        app(FlightPlanMutationService::class)->accept($flight, $atmo, 'AT');
        $this->actingAs($flight->filedBy)->get(EditFlightManifest::getUrl(['flight' => $flight], panel: 'pilot'))->assertOk()
            ->assertSeeText('Accepted filing')->assertDontSeeText('Save manifest')->assertDontSee('data-person-add', false);
        $this->put(route('flights.manifest.update', $flight), ['remarks' => 'Changed'])->assertForbidden();
        $this->assertNull($flight->manifest->fresh()->remarks);
        $this->assertSame(FlightPlanStatus::Accepted, $flight->fresh()->status);
    }

    public function test_other_pilots_cannot_open_or_write_manifest_by_flight_id(): void
    {
        $flight = Flight::factory()->create();
        $other = User::factory()->create(['role' => UserRole::Pilot]);
        $this->actingAs($other)->get(EditFlightManifest::getUrl(['flight' => $flight], panel: 'pilot'))->assertForbidden();
        $this->put(route('flights.manifest.update', $flight), $this->completeData($flight))->assertForbidden();
        $this->assertDatabaseCount('flight_manifests', 0);
    }

    public function test_inactive_users_cannot_open_manifest_selector(): void
    {
        $user = User::factory()->create(['role' => UserRole::Pilot, 'is_active' => false]);
        $this->actingAs($user)->get(route('manifests.index'))->assertForbidden();
    }

    public function test_submitted_parent_creator_and_flight_fields_cannot_be_overwritten(): void
    {
        $flight = Flight::factory()->create();
        $other = Flight::factory()->create();
        $this->actingAs($flight->filedBy)->put(route('flights.manifest.update', $flight), [
            ...$this->completeData($flight), 'flight_id' => $other->id,
            'created_by_user_id' => $other->filed_by_user_id, 'route' => 'CHANGED',
        ])->assertSessionHasNoErrors();
        $manifest = $flight->manifest;
        $this->assertSame($flight->id, $manifest->flight_id);
        $this->assertSame($flight->filed_by_user_id, $manifest->created_by_user_id);
        $this->assertSame('DCT', $flight->fresh()->route);
    }

    public function test_legacy_manifest_url_redirects_pilot_to_panel(): void
    {
        $flight = Flight::factory()->create();
        $this->actingAs($flight->filedBy)->get(route('flights.manifest.edit', $flight))
            ->assertRedirect(EditFlightManifest::getUrl(['flight' => $flight], panel: 'pilot'));
        $this->assertDatabaseCount('flight_manifests', 0);
    }

    public function test_panel_manifest_denies_inactive_and_non_pilot_users_and_missing_flights(): void
    {
        $flight = Flight::factory()->create();
        foreach ([UserRole::Pilot, UserRole::Atmo] as $role) {
            $user = User::factory()->create(['role' => $role, 'is_active' => $role !== UserRole::Pilot]);
            $this->actingAs($user)->get(EditFlightManifest::getUrl(['flight' => $flight], panel: 'pilot'))->assertForbidden();
        }
        $this->actingAs($flight->filedBy)->get(EditFlightManifest::getUrl(['flight' => 999999], panel: 'pilot'))->assertNotFound();
    }

    public function test_staff_can_still_view_existing_manifest_outside_pilot_panel(): void
    {
        $flight = Flight::factory()->create();
        app(FlightManifestService::class)->save($flight, $flight->filedBy, $this->completeData($flight));
        $atmo = User::factory()->create(['role' => UserRole::Atmo, 'station' => 'RPUS']);
        $this->actingAs($atmo)->get(route('flights.manifest.edit', $flight))
            ->assertOk()->assertSeeText('Flight manifest')->assertDontSee('fi-sidebar', false);
    }

    public function test_dispatch_manifest_urls_and_saving_stay_inside_dispatch_panel(): void
    {
        foreach ([UserRole::Dispatch, UserRole::OperatorStaff] as $role) {
            $user = User::factory()->create(['role' => $role, 'is_active' => true, 'operator_id' => Operator::factory()->create()->id]);
            $flight = Flight::factory()->create([
                'operator_id' => $user->operator_id,
                'prepared_by_user_id' => $user->id,
                'filed_by_user_id' => $user->id,
            ]);
            $listUrl = \App\Filament\Panels\Dispatch\Pages\ListFlightManifests::getUrl(panel: 'dispatch');
            $editUrl = \App\Filament\Panels\Dispatch\Pages\EditFlightManifest::getUrl(['flight' => $flight], panel: 'dispatch');
            $this->actingAs($user)->get(route('manifests.index'))->assertRedirect($listUrl);
            $this->get($listUrl)->assertOk()->assertSee('fi-sidebar', false)->assertSeeText($flight->aircraft_identification);
            $this->get(route('flights.manifest.edit', $flight))->assertRedirect($editUrl);
            $this->get($editUrl)->assertOk()->assertSee('fi-sidebar', false)->assertSeeText('Save manifest');
            $this->put(route('flights.manifest.update', $flight), $this->completeData($flight))
                ->assertSessionHasNoErrors()->assertRedirect($editUrl);
            $this->get($editUrl)->assertOk()->assertSeeText('Manifest saved.');
        }
    }

    /** @return array<string, mixed> */
    private function completeData(Flight $flight): array
    {
        return [
            'aircraft_owner_name' => 'Aircraft Owner', 'aircraft_owner_contact_number' => '+639171234567',
            'home_base_hangar' => 'RPUS', 'coa_expiry_date' => '2027-12-31', 'total_cargo_weight' => 0,
            'persons' => [['full_name' => $flight->pilot_in_command, 'nationality' => 'Filipino', 'classification' => 'pic']],
        ];
    }
}
