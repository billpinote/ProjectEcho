<?php

namespace Tests\Feature;

use App\Domain\FlightPlans\Enums\FlightPlanStatus;
use App\Domain\FlightPlans\Services\AvsecVerificationService;
use App\Domain\FlightPlans\Services\FlightManifestService;
use App\Domain\FlightPlans\Services\FlightPlanPdfService;
use App\Domain\FlightPlans\Services\FlightPlanQrPayloadService;
use App\Domain\FlightPlans\Services\FlightWorkflowService;
use App\Domain\FlightPlans\Support\FlightStatusDisplay;
use App\Domain\Users\Enums\UserRole;
use App\Filament\Panels\Atmo\Resources\AllFlightPlans\Pages\ListAllFlights;
use App\Filament\Panels\Atmo\Resources\AwaitingAvsecFlights\AwaitingAvsecFlightResource;
use App\Filament\Panels\Atmo\Resources\AwaitingAvsecFlights\Pages\ListAwaitingAvsecFlights;
use App\Filament\Panels\Atmo\Resources\Flights\FlightResource;
use App\Filament\Panels\Atmo\Resources\Flights\Pages\ListFlights;
use App\Models\Flight;
use App\Models\FlightPlanEvent;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AtmoAvsecQueuesTest extends TestCase
{
    use RefreshDatabase, \Tests\Support\SignsFlightDocuments;

    private function readyFlight(): Flight
    {
        $flight = Flight::factory()->create()->refresh();
        app(FlightManifestService::class)->save($flight, $flight->filedBy, [
            'aircraft_owner_name' => 'Owner', 'aircraft_owner_contact_number' => '1234567',
            'home_base_hangar' => 'RPUS', 'coa_expiry_date' => now()->addYear()->toDateString(), 'total_cargo_weight' => 0,
            'persons' => [['full_name' => $flight->pilot_in_command, 'nationality' => 'PH', 'classification' => 'pic']],
        ]);
        $this->signFlightDocuments($flight);

        return $flight->refresh();
    }

    private function atmo(): User
    {
        $atmo = User::factory()->create(['role' => UserRole::Atmo, 'station' => 'RPUS', 'wiresign' => 'AT']);
        $this->actingAs($atmo);
        Filament::setCurrentPanel(Filament::getPanel('atmo'));

        return $atmo;
    }

    public function test_queues_are_exclusive_and_follow_verification_without_changing_lifecycle_status(): void
    {
        $flight = $this->readyFlight();
        $this->atmo();
        $this->assertTrue(AwaitingAvsecFlightResource::getEloquentQuery()->whereKey($flight)->exists());
        $this->assertFalse(FlightResource::getEloquentQuery()->whereKey($flight)->exists());
        $this->assertSame('1', AwaitingAvsecFlightResource::getNavigationBadge());
        Livewire::test(ListAwaitingAvsecFlights::class)->assertCanSeeTableRecords([$flight])
            ->assertTableActionDoesNotExist('accept')->assertTableActionDoesNotExist('edit');
        Livewire::test(ListAllFlights::class)->assertSee('Pending AVSEC')->assertTableColumnVisible('status_presentation');
        $this->post(route('flights.accept', $flight))->assertForbidden();
        $this->get(route('flights.view', $flight))->assertOk()->assertSee('Pending AVSEC')->assertDontSee('data-open-review-modal="accept-flight-plan"', false);
        $this->assertNull($flight->fresh()->reviewed_at);
        $this->confirmFlightAvsec($flight);
        $this->assertFalse(AwaitingAvsecFlightResource::getEloquentQuery()->whereKey($flight)->exists());
        $this->assertTrue(FlightResource::getEloquentQuery()->whereKey($flight)->exists());
        $this->assertSame(FlightPlanStatus::Pending, $flight->fresh()->status);
        Livewire::test(ListFlights::class)->assertCanSeeTableRecords([$flight]);
        Livewire::test(ListAllFlights::class)->assertSee('AVSEC Approved');
        $this->get(route('flights.view', $flight))->assertOk()->assertSee('AVSEC Approved')->assertSee('data-open-review-modal="accept-flight-plan"', false);
        $this->post(route('flights.accept', $flight))->assertRedirect();
    }

    public function test_ineligible_states_never_enter_awaiting_avsec(): void
    {
        $flight = $this->readyFlight();
        foreach ([
            ['status' => FlightPlanStatus::AwaitingPic],
            ['status' => FlightPlanStatus::Pending, 'pic_authorization_archived_at' => now()],
            ['status' => FlightPlanStatus::Rejected, 'pic_authorization_archived_at' => null],
            ['status' => FlightPlanStatus::Cancelled],
        ] as $attributes) {
            $flight->forceFill($attributes)->saveQuietly();
            $this->assertNotSame(FlightStatusDisplay::AWAITING_AVSEC, FlightStatusDisplay::status($flight->fresh()));
        }
        $unsigned = Flight::factory()->create();
        $this->assertSame(FlightStatusDisplay::PENDING, FlightStatusDisplay::status($unsigned));
        $this->atmo();
        $this->assertSame(0, AwaitingAvsecFlightResource::getEloquentQuery()->count());
    }

    public function test_denied_clearance_enters_atmo_review_and_preserves_independent_decision_history(): void
    {
        $flight = $this->readyFlight();
        $officer = User::factory()->create(['role' => UserRole::Avsec, 'station' => 'RPUS']);
        $denial = app(AvsecVerificationService::class)->deny(
            app(FlightPlanQrPayloadService::class)->buildPayload($flight), $officer, 'Offline clearance denied',
        );
        $denialBefore = $denial->fresh()->getAttributes();
        $this->assertSame(FlightPlanStatus::Pending, $flight->fresh()->status);
        $this->assertSame('Clearance Denied', FlightStatusDisplay::badge($flight->fresh(), UserRole::Avsec)['label']);
        $atmo = $this->atmo();
        $this->assertFalse(AwaitingAvsecFlightResource::getEloquentQuery()->whereKey($flight)->exists());
        $this->assertTrue(FlightResource::getEloquentQuery()->whereKey($flight)->exists());
        Livewire::test(ListAwaitingAvsecFlights::class)->assertCanNotSeeTableRecords([$flight]);
        Livewire::test(ListFlights::class)->assertCanSeeTableRecords([$flight])
            ->assertTableColumnStateSet('status_presentation', 'AVSEC Denied', $flight)->assertSee('AVSEC Denied');
        $this->assertTrue($atmo->can('reject', $flight));
        $this->assertFalse($atmo->can('accept', $flight));
        $this->get(route('flights.view', $flight))->assertOk()->assertSee('AVSEC Denied')
            ->assertSee('data-open-review-modal="reject-flight-plan"', false)
            ->assertSee('disabled aria-describedby="accept-unavailable-reason"', false)
            ->assertSee('ATMO acceptance requires AVSEC approval.')
            ->assertDontSee('data-open-review-modal="accept-flight-plan"', false);
        $this->post(route('flights.accept', $flight))->assertForbidden();
        $this->assertSame(FlightPlanStatus::Pending, $flight->fresh()->status);
        $this->post(route('flights.reject', $flight), ['rejection_reason' => 'ATMO operational review declined'])
            ->assertRedirect(route('flights.view', $flight));
        $this->assertSame(FlightPlanStatus::Rejected, $flight->fresh()->status);
        $this->assertSame($denialBefore, $denial->fresh()->getAttributes());
        $this->assertSame(1, $flight->events()->where('event_type', FlightPlanEvent::TYPE_AVSEC_DENIED)->count());
        $this->assertSame(1, $flight->events()->where('event_type', FlightPlanEvent::TYPE_ATC_REJECTED)->count());
    }

    public function test_expired_and_declined_labels_take_precedence_and_station_access_is_preserved(): void
    {
        $flight = $this->readyFlight();
        $flight->forceFill(['date_of_flight' => now()->subDay()->toDateString()])->saveQuietly();
        $flight->forceFill(['status' => FlightPlanStatus::Pending])->saveQuietly();
        $this->assertSame(FlightStatusDisplay::EXPIRED, FlightStatusDisplay::status($flight->fresh()));
        $flight->forceFill(['pic_authorization_status' => 'declined'])->saveQuietly();
        $this->assertSame(FlightStatusDisplay::PIC_DECLINED, FlightStatusDisplay::status($flight->fresh()));
        $this->actingAs(User::factory()->create(['role' => UserRole::Atmo, 'station' => 'RPLL']))
            ->get('/atmo/awaiting-avsec')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => UserRole::Avsec, 'station' => 'RPUS']))
            ->get('/atmo/awaiting-avsec')->assertForbidden();
    }

    public function test_loaded_workflow_resolution_uses_no_per_row_database_queries(): void
    {
        $first = $this->readyFlight();
        $second = $this->readyFlight();
        $this->confirmFlightAvsec($second);
        $flights = app(FlightWorkflowService::class)->withStatusContext(Flight::query()->whereKey([$first->id, $second->id]))->get();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $statuses = $flights->map(fn (Flight $flight) => FlightStatusDisplay::badge($flight, UserRole::Atmo)['label'])->all();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertEqualsCanonicalizing(['Pending AVSEC', 'AVSEC Approved'], $statuses);
        $this->assertSame([], $queries);
    }

    public function test_navigation_places_awaiting_avsec_immediately_before_pending(): void
    {
        $this->atmo();
        $this->get('/atmo/awaiting-avsec')->assertOk()->assertSeeInOrder(['AVSEC Clearance', 'Pending Flight Plans']);
        $this->assertLessThan(FlightResource::getNavigationSort(), AwaitingAvsecFlightResource::getNavigationSort());
    }

    public function test_invalid_verification_does_not_enter_either_queue(): void
    {
        $flight = $this->readyFlight();
        $this->confirmFlightAvsec($flight);
        $pdfs = app(FlightPlanPdfService::class);
        Storage::disk('local')->put($pdfs->storedPaths($flight)->first(), 'tampered');
        $this->atmo();
        $this->assertFalse(AwaitingAvsecFlightResource::getEloquentQuery()->whereKey($flight)->exists());
        $this->assertFalse(FlightResource::getEloquentQuery()->whereKey($flight)->exists());
        $this->post(route('flights.accept', $flight))->assertForbidden();
    }

    public function test_existing_operational_status_labels_are_preserved(): void
    {
        foreach ([
            [[], 'Accepted'],
            [['time_start_up' => '1000'], 'Started'],
            [['time_airborne' => '1010'], 'Airborne'],
            [['time_touchdown' => '1100'], 'Landed'],
            [['time_touchdown' => '1100', 'time_block_on' => '1110'], 'Completed'],
            [['status' => FlightPlanStatus::Rejected], 'Rejected'],
            [['status' => FlightPlanStatus::Cancelled], 'Cancelled'],
        ] as [$attributes, $label]) {
            $flight = new Flight(['status' => FlightPlanStatus::Accepted, ...$attributes]);
            $this->assertSame($label, FlightStatusDisplay::badge($flight, UserRole::Atmo)['label']);
        }
    }
}
