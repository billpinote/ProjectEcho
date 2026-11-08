<?php

namespace Tests\Feature;

use App\Domain\FlightPlans\Enums\FlightPlanStatus;
use App\Domain\FlightPlans\Services\AvsecVerificationService;
use App\Domain\FlightPlans\Services\FilingAuthorizationService;
use App\Domain\FlightPlans\Services\FlightManifestService;
use App\Domain\FlightPlans\Services\FlightPlanMutationService;
use App\Domain\FlightPlans\Services\FlightPlanPdfService;
use App\Domain\FlightPlans\Services\FlightPlanQrPayloadService;
use App\Domain\FlightPlans\Support\FilingDocumentRegistry;
use App\Domain\Users\Enums\UserRole;
use App\Filament\Panels\Avsec\Pages\ImportScanQr;
use App\Filament\Panels\Avsec\Resources\AvsecVerifications\AvsecVerificationResource;
use App\Filament\Panels\Avsec\Resources\AvsecVerifications\Pages\ListAvsecVerifications;
use App\Models\AvsecVerification;
use App\Models\Flight;
use App\Models\Operator;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AvsecVerificationTest extends TestCase
{
    use RefreshDatabase, \Tests\Support\SignsFlightDocuments;

    private function officer(array $attributes = []): User
    {
        return User::factory()->create(['role' => UserRole::Avsec, 'station' => 'RPUS', ...$attributes]);
    }

    private function readyFlight(array $attributes = []): Flight
    {
        $flight = Flight::factory()->create($attributes)->refresh();
        app(FlightManifestService::class)->save($flight, $flight->filedBy, [
            'aircraft_owner_name' => 'Owner', 'aircraft_owner_contact_number' => '+639171234567',
            'home_base_hangar' => 'RPUS', 'coa_expiry_date' => now()->addYear()->toDateString(),
            'total_cargo_weight' => 0,
            'persons' => [['full_name' => $flight->pilot_in_command, 'nationality' => 'Filipino', 'classification' => 'pic']],
        ]);
        $this->signFlightDocuments($flight);

        return $flight->refresh();
    }

    private function payload(Flight $flight): string
    {
        return app(FlightPlanQrPayloadService::class)->buildPayload($flight->fresh());
    }

    private function verify(Flight $flight): AvsecVerification
    {
        return app(AvsecVerificationService::class)->confirm($this->payload($flight), $this->officer(), true, 'PNP-123');
    }

    public function test_confirmation_preserves_revision_content_and_audit_and_allows_atmo_acceptance(): void
    {
        $flight = $this->readyFlight();
        $atmo = User::factory()->create(['role' => UserRole::Atmo, 'station' => 'RPUS', 'wiresign' => 'AT']);
        $this->actingAs($atmo)->post(route('flights.accept', $flight))->assertForbidden();
        $record = $this->verify($flight);
        $this->assertSame($flight->revision_number, $record->flight_revision);
        $this->assertSame(FilingDocumentRegistry::fingerprint($record->approved_content), $record->content_sha256);
        $event = $flight->events()->where('event_type', 'avsec_verified')->sole();
        $this->assertSame($record->verifier_user_id, $event->actor_user_id);
        $this->assertSame(AvsecVerificationService::CONFIRMATION, $event->new_values['confirmation']);
        $this->assertFalse($this->officer()->can('accept', $flight));
        try {
            app(FlightPlanMutationService::class)->accept($flight, $this->officer(), 'AV');
            $this->fail('AVSEC must not exercise ATMO acceptance authority.');
        } catch (AuthorizationException) {
            $this->assertSame(FlightPlanStatus::Pending, $flight->fresh()->status);
        }
        $this->actingAs($atmo)->post(route('flights.accept', $flight))->assertRedirect(route('flights.view', $flight));
        $this->assertSame(FlightPlanStatus::Accepted, $flight->fresh()->status);
        $flight->refresh()->update(['revised_eobt' => '15:00', 'time_airborne' => '15:10']);
        $this->assertTrue(app(AvsecVerificationService::class)->validFor($flight->fresh()));
        $this->assertSame(1, $flight->avsecVerification()->count());
    }

    #[DataProvider('ineligibleActors')]
    public function test_only_active_rpus_avsec_can_confirm(string $role, string $station, bool $active): void
    {
        $flight = $this->readyFlight();
        $actor = $this->officer(['role' => $role, 'station' => $station, 'is_active' => $active]);
        $this->expectException(AuthorizationException::class);
        app(AvsecVerificationService::class)->confirm($this->payload($flight), $actor, true);
    }

    public static function ineligibleActors(): array
    {
        return [['AVSEC', 'RPLL', true], ['AVSEC', 'RPUS', false], ['ATMO', 'RPUS', true], ['ADMIN', 'RPUS', true], ['PILOT', 'RPUS', true], ['ARTISAN', 'RPUS', true]];
    }

    #[DataProvider('invalidScans')]
    public function test_invalid_stale_unsigned_and_snapshot_only_qrs_cannot_confirm(string $kind): void
    {
        $flight = $this->readyFlight();
        $payload = $this->payload($flight);
        if ($kind === 'stale') {
            $flight->update(['route' => 'DCT CHANGED']);
        } elseif ($kind === 'revision') {
            $flight->forceFill(['revision_number' => $flight->revision_number + 1])->saveQuietly();
        } elseif ($kind === 'snapshot') {
            $copy = $flight->replicate();
            $copy->forceFill(['id' => 999999]);
            $copy->exists = true;
            $payload = app(FlightPlanQrPayloadService::class)->buildPayload($copy);
        } elseif ($kind === 'unsigned') {
            $payload = 'ECHOFPL|1|DB|'.$flight->id;
        } else {
            $payload = substr($payload, 0, -10).'tampered';
        }
        $this->expectException(ValidationException::class);
        app(AvsecVerificationService::class)->confirm($payload, $this->officer(), true);
    }

    public static function invalidScans(): array
    {
        return array_map(fn ($kind) => [$kind], ['stale', 'revision', 'snapshot', 'unsigned', 'invalid']);
    }

    #[DataProvider('missingPrerequisites')]
    public function test_prerequisites_are_enforced(string $kind): void
    {
        $flight = $this->readyFlight();
        if ($kind === 'manifest') {
            $flight->manifest->delete();
        } elseif ($kind === 'manifest_unsigned') {
            $flight->manifest->update(['cargo_type' => 'CHANGED']);
        } elseif ($kind === 'unsigned') {
            $flight->update(['route' => 'CHANGED']);
        } elseif ($kind === 'expired') {
            $flight->update(['date_of_flight' => now()->subDay()->toDateString()]);
        } elseif ($kind === 'incomplete') {
            $flight->manifest->update(['aircraft_owner_name' => null]);
            $packages = app(FilingAuthorizationService::class);
            $pilot = $flight->pilotInCommandUser;
            $packages->decide($packages->request($flight), $flight, $pilot, true, null, $pilot->approvedSignature);
            $flight->forceFill(['status' => FlightPlanStatus::Pending])->saveQuietly();
        }
        $this->expectException(ValidationException::class);
        app(AvsecVerificationService::class)->confirm($this->payload($flight), $this->officer(), $kind !== 'consent');
    }

    public static function missingPrerequisites(): array
    {
        return array_map(fn ($kind) => [$kind], ['manifest', 'manifest_unsigned', 'unsigned', 'expired', 'incomplete', 'consent']);
    }

    public function test_duplicate_confirmation_does_not_overwrite_evidence(): void
    {
        $flight = $this->readyFlight();
        $first = $this->verify($flight);
        try {
            $this->verify($flight);
            $this->fail('Duplicate confirmation accepted');
        } catch (ValidationException) {
            $this->assertSame($first->id, $flight->avsecVerification()->sole()->id);
            $this->assertSame(1, $flight->events()->where('event_type', 'avsec_verified')->count());
        }
        $this->expectException(QueryException::class);
        DB::table('avsec_verifications')->insert([...$first->getAttributes(), 'id' => $first->id + 1]);
    }

    #[DataProvider('frozenMutations')]
    public function test_frozen_submission_rejects_model_quiet_and_bulk_mutations(string $kind): void
    {
        $flight = $this->readyFlight();
        $record = $this->verify($flight);
        $manifest = $flight->manifest()->first();
        $person = $manifest->persons()->first();
        $this->expectException(in_array($kind, ['model', 'quiet', 'pdf', 'manifest_model', 'person_model']) ? ValidationException::class : QueryException::class);
        match ($kind) {
            'model' => $flight->update(['route' => 'CHANGED']),
            'quiet' => $flight->forceFill(['revision_number' => 99])->saveQuietly(),
            'bulk' => Flight::whereKey($flight->id)->update(['route' => 'CHANGED']),
            'delete' => DB::table('flights')->where('id', $flight->id)->delete(),
            'manifest_model' => $manifest->update(['cargo_type' => 'CHANGED']),
            'manifest_bulk' => DB::table('flight_manifests')->where('id', $manifest->id)->update(['cargo_type' => 'CHANGED']),
            'person_model' => $person->update(['full_name' => 'CHANGED']),
            'person_bulk' => DB::table('manifest_persons')->where('id', $person->id)->delete(),
            'person_insert' => DB::table('manifest_persons')->insert(['flight_manifest_id' => $manifest->id, 'sequence_number' => 2, 'full_name' => 'NEW', 'classification' => 'passenger']),
            'evidence_update' => DB::table('avsec_verifications')->where('id', $record->id)->update(['pnp_reference_number' => 'CHANGED']),
            'evidence_delete' => DB::table('avsec_verifications')->where('id', $record->id)->delete(),
            'package' => DB::table('filing_authorizations')->where('flight_id', $flight->id)->update(['pdf_documents' => 'CHANGED']),
            'audit' => DB::table('flight_plan_events')->where('flight_id', $flight->id)->delete(),
            'pdf' => app(FlightPlanPdfService::class)->deleteExisting($flight),
        };
    }

    public static function frozenMutations(): array
    {
        return array_map(fn ($kind) => [$kind], ['model', 'quiet', 'bulk', 'delete', 'manifest_model', 'manifest_bulk', 'person_model', 'person_bulk', 'person_insert', 'evidence_update', 'evidence_delete', 'package', 'audit', 'pdf']);
    }

    public function test_rejection_does_not_unlock_and_register_is_shared_across_shifts(): void
    {
        $flight = $this->readyFlight();
        $record = $this->verify($flight);
        $atmo = User::factory()->create(['role' => UserRole::Atmo, 'station' => 'RPUS']);
        app(FlightPlanMutationService::class)->reject($flight, $atmo, 'AT', 'Review needed');
        $this->assertTrue($flight->fresh()->isAvsecFrozen());
        $this->assertFalse($atmo->can('update', $flight));
        Filament::setCurrentPanel(Filament::getPanel('avsec'));
        Livewire::actingAs($this->officer())->test(ListAvsecVerifications::class)->assertSuccessful()->assertCanSeeTableRecords([$record])
            ->searchTable($flight->aircraft_identification)->assertCanSeeTableRecords([$record])
            ->filterTable('flight_status', FlightPlanStatus::Rejected->value)->assertCanSeeTableRecords([$record]);
        $this->actingAs($this->officer())->get(AvsecVerificationResource::getUrl('view', ['record' => $record], panel: 'avsec'))
            ->assertOk()->assertSee('PNP-123')->assertSee('Confirmed (Asia/Manila)');
        Livewire::actingAs($this->officer())->test(ImportScanQr::class)->set('payload', $this->payload($flight))->call('submit')
            ->assertSet('matchedFlight.verified', true)->assertSee('AVSEC confirmed');
        $this->actingAs($this->officer(['station' => 'RPLL']))->get('/avsec/avsec-verifications')->assertForbidden();
    }

    public function test_arrivals_from_other_stations_are_outside_avsec_scope(): void
    {
        $flight = Flight::factory()->create(['departure_aerodrome' => 'RPLL', 'destination_aerodrome' => 'RPUS']);
        $this->assertFalse(AvsecVerificationService::requiredFor($flight));
        $this->assertFalse($this->officer()->can('view', $flight));
        $this->expectException(AuthorizationException::class);
        app(AvsecVerificationService::class)->resolve($this->payload($flight), $this->officer());
    }

    public function test_preserved_pdf_tampering_invalidates_verification_and_blocks_acceptance(): void
    {
        $flight = $this->readyFlight();
        $this->verify($flight);
        $path = app(FlightPlanPdfService::class)->storedPaths($flight)->first();
        Storage::disk('local')->put($path, 'tampered');
        $this->assertFalse(app(AvsecVerificationService::class)->validFor($flight));
        $this->expectException(ValidationException::class);
        app(FlightPlanMutationService::class)->accept($flight, User::factory()->create(['role' => UserRole::Atmo, 'station' => 'RPUS']), 'AT');
    }

    public function test_revision_and_eobt_remain_stable_across_a_permitted_delay(): void
    {
        $flight = $this->readyFlight();
        $record = $this->verify($flight);
        app(FlightPlanMutationService::class)->delay($flight, $flight->filedBy, '1530');
        $this->assertSame($record->flight_revision, $flight->fresh()->revision_number);
        $this->assertTrue(app(AvsecVerificationService::class)->validFor($flight->fresh()));
    }

    public function test_non_rpus_departures_keep_the_existing_acceptance_rules(): void
    {
        $flight = $this->readyFlight(['departure_aerodrome' => 'RPLL', 'destination_aerodrome' => 'RPUS']);
        app(FlightPlanMutationService::class)->accept($flight, User::factory()->create(['role' => UserRole::Atmo, 'station' => 'RPUS']), 'AT');
        $this->assertSame(FlightPlanStatus::Accepted, $flight->fresh()->status);
        $this->assertFalse($flight->isAvsecFrozen());
    }

    public function test_audit_failure_rolls_back_confirmation_and_manifest_snapshot(): void
    {
        $flight = $this->readyFlight();
        DB::unprepared("CREATE TRIGGER fail_avsec_event BEFORE INSERT ON flight_plan_events WHEN NEW.event_type = 'avsec_verified' BEGIN SELECT RAISE(ABORT, 'Simulated AVSEC audit failure'); END");
        try {
            $this->verify($flight);
            $this->fail('Confirmation must roll back if its audit event fails.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('Simulated AVSEC audit failure', $exception->getMessage());
        } finally {
            DB::unprepared('DROP TRIGGER fail_avsec_event');
        }
        $this->assertFalse($flight->isAvsecFrozen());
        $this->assertNull($flight->manifest()->first()->accepted_pdf_content);
        $this->assertSame(0, $flight->events()->where('event_type', 'avsec_verified')->count());
    }

    #[DataProvider('referencedParents')]
    public function test_parent_deletion_cannot_null_frozen_content_or_audit_references(string $parent): void
    {
        $user = User::factory()->create(['role' => UserRole::Dispatch]);
        $operator = Operator::factory()->create();
        $flight = $this->readyFlight(['prepared_by_user_id' => $user->id, 'operator_id' => $operator->id]);
        $this->verify($flight);
        $this->expectException(QueryException::class);
        DB::table($parent === 'user' ? 'users' : 'operators')->where('id', $parent === 'user' ? $user->id : $operator->id)->delete();
    }

    public static function referencedParents(): array
    {
        return [['user'], ['operator']];
    }
}
