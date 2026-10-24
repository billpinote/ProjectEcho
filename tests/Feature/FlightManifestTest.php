<?php

namespace Tests\Feature;

use App\Domain\FlightPlans\Enums\FlightPlanStatus;
use App\Domain\FlightPlans\Services\FlightManifestService;
use App\Domain\FlightPlans\Services\FlightPlanMutationService;
use App\Domain\FlightPlans\Support\FlightManifestData;
use App\Domain\Users\Enums\UserRole;
use App\Models\Flight;
use App\Models\FlightManifest;
use App\Models\FlightPlanEvent;
use App\Models\ManifestPerson;
use App\Models\Operator;
use App\Models\User;
use Database\Seeders\FlightManifestSeeder;
use Database\Seeders\ManifestPersonSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FlightManifestTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_relationships_defaults_and_repeated_saves_do_not_duplicate_pic(): void
    {
        $flight = Flight::factory()->create();
        $service = app(FlightManifestService::class);
        $manifest = $service->save($flight, $flight->filedBy, []);
        $service->save($flight, $flight->filedBy, ['remarks' => 'Draft']);

        $this->assertTrue($flight->manifest->is($manifest));
        $this->assertTrue($manifest->flight->is($flight));
        $this->assertTrue($manifest->creator->is($flight->filedBy));
        $this->assertCount(1, $manifest->fresh()->persons);
        $this->assertTrue($manifest->persons->first()->flightManifest->is($manifest));
        $this->assertSame('pic', $manifest->persons->first()->classification);
        $this->assertSame($flight->pilot_in_command, $manifest->persons->first()->full_name);
    }

    #[DataProvider('atmoMutations')]
    public function test_atmo_cannot_mutate_manifests_through_the_shared_service(string $operation): void
    {
        $flight = Flight::factory()->create();
        $atmo = $this->atmo();
        $manifest = $operation === 'create' ? null : $this->completeManifest($flight);
        if ($operation === 'reorder_persons') {
            $manifest = app(FlightManifestService::class)->save($flight, $flight->filedBy, ['persons' => [
                ['full_name' => $flight->pilot_in_command, 'classification' => 'pic'],
                ['full_name' => 'Existing crew', 'classification' => 'crew'],
            ]]);
        }
        $before = $manifest?->fresh()->getAttributes();
        $persons = $manifest?->persons->toArray();
        $service = app(FlightManifestService::class);
        $this->assertFalse($atmo->can('create', [FlightManifest::class, $flight]));
        if ($manifest !== null) {
            $this->assertTrue($atmo->can('view', $manifest));
            $this->assertFalse($atmo->can('update', $manifest));
            $this->assertFalse($atmo->can('delete', $manifest));
        }
        try {
            match ($operation) {
                'delete' => $service->delete($flight, $atmo),
                'remove_persons' => $service->save($flight, $atmo, ['persons' => []]),
                'replace_persons' => $service->save($flight, $atmo, ['persons' => [['full_name' => 'Replacement', 'classification' => 'pic']]]),
                'add_person' => $service->save($flight, $atmo, ['persons' => [
                    ...$manifest->persons->map->only(['full_name', 'nationality', 'classification'])->all(),
                    ['full_name' => 'Added crew', 'classification' => 'crew'],
                ]]),
                'reorder_persons' => $service->save($flight, $atmo, ['persons' => array_reverse($manifest->persons->map->only(['full_name', 'nationality', 'classification'])->all())]),
                default => $service->save($flight, $atmo, ['remarks' => 'Not permitted']),
            };
            $this->fail('ATMO manifest mutation was permitted.');
        } catch (AuthorizationException) {
            $this->assertSame($before, $manifest?->fresh()->getAttributes());
            $this->assertSame($persons, $manifest?->fresh()->persons->toArray());
            $this->assertDatabaseCount('flight_manifests', $manifest === null ? 0 : 1);
        }
    }

    public static function atmoMutations(): array
    {
        return array_map(fn (string $operation): array => [$operation], ['create', 'update', 'delete', 'remove_persons', 'replace_persons', 'add_person', 'reorder_persons']);
    }

    public function test_existing_administrative_manifest_permissions_remain_subject_to_acceptance_lock(): void
    {
        $flight = Flight::factory()->create();
        $manifest = $this->completeManifest($flight);
        foreach ([UserRole::Admin, UserRole::Artisan] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->assertTrue($user->can('create', [FlightManifest::class, $flight]));
            $this->assertTrue($user->can('update', $manifest));
            $this->assertTrue($user->can('delete', $manifest));
        }
        app(FlightPlanMutationService::class)->accept($flight, $this->atmo(), 'AT');
        foreach ([UserRole::Admin, UserRole::Artisan] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->assertFalse($user->can('update', $manifest->fresh()));
            $this->assertFalse($user->can('delete', $manifest->fresh()));
        }
    }

    public function test_operator_staff_can_review_but_cannot_manage_manifests(): void
    {
        $staff = User::factory()->create(['role' => UserRole::OperatorStaff]);
        $flight = Flight::factory()->create(['prepared_by_user_id' => $staff->id]);
        $manifest = $this->completeManifest($flight);
        $this->assertTrue($staff->can('view', $manifest));
        $this->assertFalse($staff->can('create', [FlightManifest::class, $flight]));
        $this->assertFalse($staff->can('update', $manifest));
        $this->assertFalse($staff->can('delete', $manifest));
    }

    public function test_one_manifest_per_flight_is_a_database_constraint(): void
    {
        $manifest = FlightManifest::factory()->create();
        $this->expectException(QueryException::class);
        FlightManifest::factory()->for($manifest->flight)->create();
    }

    public function test_manifest_requires_an_existing_flight_and_creator(): void
    {
        $this->expectException(QueryException::class);
        DB::table('flight_manifests')->insert(['flight_id' => 999, 'created_by_user_id' => 999]);
    }

    public function test_person_order_and_more_than_twenty_five_rows_are_supported(): void
    {
        $flight = Flight::factory()->create(['persons_on_board' => 30]);
        $persons = array_map(fn (int $index): array => [
            'full_name' => 'Passenger '.$index, 'nationality' => 'Filipino', 'classification' => 'passenger',
        ], range(1, 29));
        $persons[] = ['full_name' => strtolower($flight->pilot_in_command), 'nationality' => 'Filipino', 'classification' => 'crew'];
        $persons[] = ['full_name' => $flight->pilot_in_command, 'nationality' => 'Filipino', 'classification' => 'pic'];
        $manifest = app(FlightManifestService::class)->save($flight, $flight->filedBy, ['persons' => $persons]);

        $this->assertCount(30, $manifest->persons);
        $this->assertSame(range(1, 30), $manifest->persons->pluck('sequence_number')->all());
        $this->assertSame('pic', $manifest->persons->first()->classification);
        $this->assertSame('Passenger 1', $manifest->persons[1]->full_name);
        $this->assertTrue(app(FlightManifestService::class)->comparison($manifest)['matches']);
        $flight->update(['persons_on_board' => 31]);
        $this->assertFalse(app(FlightManifestService::class)->comparison($manifest->fresh())['matches']);
        $this->assertSame(30, $manifest->persons()->count());
        $this->assertSame(31, $flight->fresh()->persons_on_board);
    }

    public function test_mapping_uses_filed_credentials_and_does_not_treat_operator_as_home_base(): void
    {
        $flight = Flight::factory()->create(['other_info_expiry_date_to_operate' => '2027-12-31']);
        $flight->filedBy->pilotProfile()->create(['operator' => 'Not a hangar', 'license_number' => 'CHANGED']);
        $data = app(FlightManifestData::class);
        $this->assertSame($flight->pilot_license_no, $data->fromFlight($flight)['pilot_license_no']);
        $this->assertSame('2027-12-31', $data->defaults($flight)['coa_expiry_date']);
        $this->assertNull($data->defaults($flight)['home_base_hangar']);
        $flight->refresh();
        app(FlightManifestService::class)->save($flight, $flight->filedBy, ['home_base_hangar' => 'RPUS']);
        $this->assertSame($flight->getAttributes(), $flight->fresh()->getAttributes());
    }

    #[DataProvider('incompleteManifests')]
    public function test_acceptance_blocks_missing_or_incomplete_manifests_without_side_effects(string $case): void
    {
        Storage::fake('public');
        $flight = Flight::factory()->create();
        if ($case !== 'missing') {
            if ($case === 'empty') {
                $manifest = new FlightManifest;
                $manifest->flight()->associate($flight);
                $manifest->creator()->associate($flight->filedBy);
                $manifest->save();
            } else {
                $manifest = $this->completeManifest($flight);
            }
            if ($case === 'nationality') {
                $manifest->persons->first()->update(['nationality' => null]);
            } elseif ($case === 'pob') {
                $flight->update(['persons_on_board' => 2]);
            } elseif ($case === 'pic') {
                $manifest->persons->first()->update(['full_name' => 'Different PIC']);
            } elseif ($case === 'cargo') {
                $manifest->update(['total_cargo_weight' => 5]);
            }
        }
        $before = $flight->fresh()->getAttributes();
        $eventsBefore = $flight->events()->count();
        $this->actingAs($this->atmo())->from(route('flights.view', $flight))
            ->post(route('flights.accept', $flight))
            ->assertRedirect(route('flights.view', $flight))->assertSessionHasErrors('manifest');
        $this->assertSame($before, $flight->fresh()->getAttributes());
        $this->assertSame($eventsBefore, $flight->events()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->get(route('flights.view', $flight))->assertSee('role="alert"', false);
    }

    public static function incompleteManifests(): array
    {
        return array_map(fn (string $case): array => [$case], ['missing', 'empty', 'nationality', 'pob', 'pic', 'cargo']);
    }

    public function test_complete_manifest_allows_acceptance_and_remains_viewable(): void
    {
        Storage::fake('public');
        $flight = Flight::factory()->create();
        $manifest = $this->completeManifest($flight);
        $atmo = $this->atmo();
        $this->actingAs($atmo)->post(route('flights.accept', $flight))->assertSessionHasNoErrors();
        $this->assertSame(FlightPlanStatus::Accepted, $flight->fresh()->status);
        $this->assertSame($atmo->id, $flight->fresh()->accepted_by_user_id);
        $this->assertSame(1, $flight->events()->where('event_type', FlightPlanEvent::TYPE_ATC_ACCEPTED)->count());
        $this->assertTrue(app(FlightManifestService::class)->view($flight, $flight->filedBy)->is($manifest));
        $this->assertFalse($flight->filedBy->can('update', $manifest->fresh()));
        $this->assertNotEmpty(Storage::disk('public')->allFiles());
    }

    #[DataProvider('lockedMutations')]
    public function test_accepted_filings_reject_model_and_bulk_mutations(string $mutation): void
    {
        $flight = Flight::factory()->create();
        $manifest = $this->completeManifest($flight);
        app(FlightPlanMutationService::class)->accept($flight, $this->atmo(), 'AT');
        $this->expectException(in_array($mutation, ['bulk_person', 'bulk_manifest', 'bulk_flight', 'bulk_delete'], true) ? QueryException::class : ValidationException::class);
        match ($mutation) {
            'manifest' => $manifest->update(['remarks' => 'Changed']),
            'delete' => $manifest->delete(),
            'person' => $manifest->persons->first()->update(['full_name' => 'Changed']),
            'reorder' => $manifest->persons->first()->update(['sequence_number' => 10]),
            'remove' => $manifest->persons->first()->delete(),
            'add' => $manifest->persons()->create(['sequence_number' => 2, 'full_name' => 'Extra']),
            'flight' => $flight->refresh()->update(['route' => 'CHANGED']),
            'flight_delete' => $flight->refresh()->delete(),
            'bulk_person' => $manifest->persons()->update(['nationality' => 'Changed']),
            'bulk_manifest' => $flight->manifest()->update(['remarks' => 'Changed']),
            'bulk_flight' => Flight::query()->whereKey($flight)->update(['route' => 'CHANGED']),
            'bulk_delete' => $flight->manifest()->delete(),
        };
    }

    public static function lockedMutations(): array
    {
        return array_map(fn (string $case): array => [$case], ['manifest', 'delete', 'person', 'reorder', 'remove', 'add', 'flight', 'flight_delete', 'bulk_person', 'bulk_manifest', 'bulk_flight', 'bulk_delete']);
    }

    public function test_acceptance_lock_survives_cancellation_and_stale_models(): void
    {
        $flight = Flight::factory()->create();
        $manifest = $this->completeManifest($flight);
        app(FlightPlanMutationService::class)->accept($flight, $this->atmo(), 'AT');
        $flight->refresh()->update(['status' => FlightPlanStatus::Cancelled]);
        $this->assertTrue($flight->fresh()->hasBeenAccepted());
        $this->expectException(ValidationException::class);
        $manifest->update(['remarks' => 'Stale draft']);
    }

    public function test_authorized_draft_edits_and_deletion_work(): void
    {
        $flight = Flight::factory()->create();
        $service = app(FlightManifestService::class);
        $manifest = $this->completeManifest($flight);
        $service->save($flight, $flight->filedBy, ['remarks' => 'Updated']);
        $this->assertSame('Updated', $manifest->fresh()->remarks);
        $service->delete($flight, $flight->filedBy);
        $this->assertDatabaseMissing('flight_manifests', ['id' => $manifest->id]);
        $this->assertDatabaseCount('manifest_persons', 0);
    }

    public function test_unrelated_or_inactive_users_cannot_read_or_write_manifests(): void
    {
        $flight = Flight::factory()->create();
        $manifest = $this->completeManifest($flight);
        $other = User::factory()->create(['role' => UserRole::Pilot, 'is_active' => true]);
        $this->assertFalse($other->can('view', $manifest));
        $this->assertFalse($other->can('update', $manifest));
        $this->assertFalse($other->can('create', [FlightManifest::class, $flight]));
        $flight->filedBy->update(['is_active' => false]);
        $this->assertFalse($flight->filedBy->can('view', $manifest));
        $this->expectException(AuthorizationException::class);
        app(FlightManifestService::class)->save($flight, $other, ['remarks' => 'Denied']);
    }

    public function test_pure_guest_compatible_validation_does_not_persist_data(): void
    {
        app(FlightManifestData::class)->validate(['aircraft_owner_name' => 'Guest']);
        $this->assertDatabaseCount('flight_manifests', 0);
        $this->assertDatabaseCount('manifest_persons', 0);
    }

    public function test_manifest_service_denies_unrelated_reads(): void
    {
        $flight = Flight::factory()->create();
        $this->completeManifest($flight);
        $other = User::factory()->create(['role' => UserRole::Pilot]);
        $this->expectException(AuthorizationException::class);
        app(FlightManifestService::class)->view($flight, $other);
    }

    public function test_dispatch_manifest_access_is_scoped_to_its_operator(): void
    {
        $operator = Operator::factory()->create();
        $flight = Flight::factory()->create(['operator_id' => $operator->id]);
        $dispatch = User::factory()->create(['role' => UserRole::Dispatch, 'operator_id' => $operator->id]);
        $manifest = app(FlightManifestService::class)->save($flight, $dispatch, []);
        $this->assertTrue($dispatch->can('update', $manifest));
        $dispatch->update(['operator_id' => Operator::factory()->create()->id]);
        $this->assertFalse($dispatch->can('view', $manifest));
        $this->assertFalse($dispatch->can('update', $manifest));
    }

    public function test_invalid_draft_is_rejected_without_creating_records(): void
    {
        $flight = Flight::factory()->create();
        try {
            app(FlightManifestService::class)->save($flight, $flight->filedBy, ['total_cargo_weight' => -1]);
            $this->fail('Invalid cargo weight was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('total_cargo_weight', $exception->errors());
        }
        $this->assertDatabaseCount('flight_manifests', 0);
        $this->assertDatabaseCount('manifest_persons', 0);
    }

    public function test_manifest_parent_cannot_be_changed(): void
    {
        $manifest = FlightManifest::factory()->create();
        $manifest->flight()->associate(Flight::factory()->create());
        $this->expectException(ValidationException::class);
        $manifest->save();
    }

    public function test_person_cannot_be_moved_to_another_manifest(): void
    {
        $person = ManifestPerson::factory()->create();
        $person->flightManifest()->associate(FlightManifest::factory()->create());
        $this->expectException(ValidationException::class);
        $person->save();
    }

    public function test_manifest_person_sequence_is_unique_within_the_manifest(): void
    {
        $person = ManifestPerson::factory()->create();
        $this->expectException(QueryException::class);
        ManifestPerson::factory()->for($person->flightManifest, 'flightManifest')->create();
    }

    public function test_acceptance_requires_current_pic_authorization_even_with_complete_manifest(): void
    {
        $flight = Flight::factory()->create(['prepared_by_user_id' => User::factory()->create(['role' => UserRole::OperatorStaff])->id]);
        $this->completeManifest($flight);
        $this->actingAs($this->atmo())->post(route('flights.accept', $flight))->assertForbidden();
        $this->assertSame(FlightPlanStatus::Pending, $flight->fresh()->status);
    }

    public function test_locked_manifest_service_rejects_replacement(): void
    {
        $flight = Flight::factory()->create();
        $manifest = $this->completeManifest($flight);
        app(FlightPlanMutationService::class)->accept($flight, $this->atmo(), 'AT');
        $this->expectException(AuthorizationException::class);
        app(FlightManifestService::class)->save($flight, $manifest->creator, ['persons' => []]);
    }

    public function test_manifest_seeders_are_opt_in_and_idempotent_and_skip_accepted_flights(): void
    {
        $flight = Flight::factory()->create();
        $accepted = Flight::factory()->create(['status' => FlightPlanStatus::Accepted]);
        $this->seed(FlightManifestSeeder::class);
        $this->seed(FlightManifestSeeder::class);
        $this->seed(ManifestPersonSeeder::class);
        $this->assertSame(1, $flight->manifest()->count());
        $this->assertSame(1, $flight->manifest->persons()->count());
        $this->assertNull($accepted->manifest);
    }

    private function completeManifest(Flight $flight): FlightManifest
    {
        return app(FlightManifestService::class)->save($flight, $flight->filedBy, [
            'aircraft_owner_name' => 'Owner', 'aircraft_owner_contact_number' => '+639171234567',
            'home_base_hangar' => 'RPUS', 'coa_expiry_date' => now()->addYear()->toDateString(),
            'total_cargo_weight' => 0,
            'persons' => [['full_name' => $flight->pilot_in_command, 'nationality' => 'Filipino', 'classification' => 'pic']],
        ]);
    }

    private function atmo(): User
    {
        return User::factory()->create(['role' => UserRole::Atmo, 'station' => 'RPUS', 'wiresign' => 'AT', 'is_active' => true]);
    }
}
