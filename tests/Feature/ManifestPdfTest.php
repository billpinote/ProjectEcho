<?php

namespace Tests\Feature;

use App\Domain\FlightPlans\Enums\FlightPlanStatus;
use App\Domain\FlightPlans\Services\FlightManifestService;
use App\Domain\FlightPlans\Services\FlightPlanMutationService;
use App\Domain\FlightPlans\Services\ManifestPdfService;
use App\Domain\Users\Enums\UserRole;
use App\Models\Flight;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use setasign\Fpdi\PdfParser\PdfParser;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\PdfReader\PdfReader;
use Tests\TestCase;

class ManifestPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_pdf_is_associated_with_flight_and_uses_filed_credentials(): void
    {
        $flight = Flight::factory()->create(['aircraft_identification' => 'RPCSELECTED', 'pilot_license_no' => 'FILED123']);
        Flight::factory()->create(['aircraft_identification' => 'RPCOTHER']);
        $manifest = app(FlightManifestService::class)->save($flight, $flight->filedBy, ['home_base_hangar' => 'Aircraft Hangar']);
        $flight->filedBy->pilotProfile()->create(['license_number' => 'CHANGED123', 'operator' => 'Pilot Operator']);
        $response = $this->actingAs($flight->filedBy)->get(route('flights.manifest.pdf', $flight))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $text = $this->streams($response->getContent());
        $this->assertStringContainsString($this->encoded('RPCSELECTED'), $text);
        $this->assertStringContainsString($this->encoded('FILED123'), $text);
        $this->assertStringContainsString($this->encoded('Aircraft Hangar'), $text);
        $this->assertStringNotContainsString($this->encoded('RPCOTHER'), $text);
        $this->assertStringNotContainsString($this->encoded('CHANGED123'), $text);
        $this->assertStringNotContainsString($this->encoded('Pilot Operator'), $text);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->get(route('flights.manifest.pdf', ['flight' => $flight, 'download' => 1]))->assertDownload('manifest-flight-'.$flight->id.'.pdf');
        $this->assertCount(1, $manifest->persons);
    }

    public function test_pdf_access_reuses_manifest_policy(): void
    {
        $flight = Flight::factory()->create();
        app(FlightManifestService::class)->save($flight, $flight->filedBy, []);
        $url = route('flights.manifest.pdf', $flight);
        $this->get($url)->assertRedirect(route('gateway'));
        $this->actingAs(User::factory()->create(['role' => UserRole::Pilot]))->get($url)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => UserRole::Pilot, 'is_active' => false]))->get($url)->assertForbidden();
        $this->actingAs($this->atmo())->get($url)->assertOk();
        $this->get(route('flights.manifest.pdf', ['flight' => 999999]))->assertNotFound();
    }

    #[DataProvider('personCounts')]
    public function test_letter_dimensions_order_numbering_and_unchanged_instructions(int $count, int $pages): void
    {
        $data = $this->guestData($count);
        $pdf = app(ManifestPdfService::class)->render($data);
        $reader = new PdfReader(new PdfParser(StreamReader::createByString($pdf)));
        $this->assertSame($pages, $reader->getPageCount());
        for ($i = 1; $i <= $pages; $i++) {
            $size = $reader->getPage($i)->getWidthAndHeight();
            $this->assertEqualsWithDelta(612, $size[0], 0.01);
            $this->assertEqualsWithDelta(792, $size[1], 0.01);
        }
        $original = new PdfReader(new PdfParser(StreamReader::createByFile(app(ManifestPdfService::class)->templatePath())));
        $streams = $this->streams($pdf);
        $this->assertStringContainsString($original->getPage(2)->getContentStream(), $streams);
        $this->assertSame(2, substr_count($streams, $this->encoded('Guest PIC')));
        $last = -1;
        foreach ($data['persons'] as $person) {
            $position = strpos($streams, $this->encoded($person['full_name']), $last + 1);
            $this->assertNotFalse($position);
            $this->assertGreaterThan($last, $position);
            $last = $position;
        }
        if ($count > 25) {
            $this->assertStringContainsString($this->encoded('26'), $streams);
            $this->assertStringContainsString($this->encoded('Nationality'), $streams);
        }
        $this->assertStringNotContainsString($this->encoded('Passenger '.($count + 1)), $streams);
    }

    public static function personCounts(): array
    {
        return [[1, 2], [4, 2], [25, 2], [26, 3], [51, 4]];
    }

    public function test_long_values_are_preserved_on_supplement_without_clipping_or_truncation(): void
    {
        $data = $this->guestData();
        $long = str_repeat('Alexandra ', 24).'ZEND';
        $data['persons'][] = ['full_name' => $long, 'nationality' => 'Spanish', 'classification' => 'passenger'];
        $bytes = app(ManifestPdfService::class)->render($data);
        $this->assertStringContainsString($this->encoded('See full field values supplement'), $this->streams($bytes));
        $this->assertStringContainsString($this->encoded('ZEND'), $this->streams($bytes));
        $reader = new PdfReader(new PdfParser(StreamReader::createByString($bytes)));
        $this->assertSame(3, $reader->getPageCount());
    }

    public function test_guest_pdf_and_validation_never_persist_input_or_flash_it_to_session(): void
    {
        Storage::fake('local');
        $this->post(route('manifests.guest.pdf'), $this->guestData())->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertCookieMissing(config('session.cookie'));
        $this->post(route('manifests.guest.pdf'), ['aircraft_identification' => 'PRIVATEVALUE'])->assertStatus(422)->assertJsonValidationErrors(['type_of_aircraft', 'persons'])->assertCookieMissing(config('session.cookie'));
        $this->assertDatabaseCount('flights', 0);
        $this->assertDatabaseCount('flight_manifests', 0);
        $this->assertDatabaseCount('manifest_persons', 0);
        $this->assertDatabaseCount('flight_manifests', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_guest_limits_and_duplicate_pic_validation(): void
    {
        $data = $this->guestData();
        $data['persons'][] = $data['persons'][0];
        $this->postJson(route('manifests.guest.pdf'), $data)->assertUnprocessable()->assertJsonValidationErrors('persons');
        $this->postJson(route('manifests.guest.pdf'), $this->guestData(251))->assertUnprocessable()->assertJsonValidationErrors('persons');
        $this->call('POST', '/manifest/guest/pdf', [], [], [], ['CONTENT_TYPE' => 'application/json', 'CONTENT_LENGTH' => 262145], str_repeat('x', 262145))->assertStatus(413);
    }

    public function test_guest_endpoint_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson(route('manifests.guest.pdf'), [])->assertUnprocessable();
        }
        $this->postJson(route('manifests.guest.pdf'), [])->assertStatus(429);
    }

    public function test_blank_download_is_original_bytes_without_database_writes(): void
    {
        $this->get(route('manifests.blank'))->assertRedirect(route('gateway'));
        $user = User::factory()->create(['role' => UserRole::Pilot]);
        $response = $this->actingAs($user)->get(route('manifests.blank'))->assertOk()->assertDownload();
        $this->assertSame(file_get_contents(app(ManifestPdfService::class)->templatePath()), $response->getContent());
        $this->assertDatabaseCount('flight_manifests', 0);
        $this->get(route('manifests.guest'))->assertOk()->assertSee('Guest flight manifest PDF');
    }

    public function test_acceptance_captures_snapshot_and_later_download_never_renders_again(): void
    {
        $flight = $this->completeFlight();
        app(FlightPlanMutationService::class)->accept($flight, $this->atmo(), 'AT');
        $flight->refresh();
        $service = app(ManifestPdfService::class);
        $expected = $service->forFlight($flight, $flight->manifest->load('persons'));
        $this->assertNotNull($flight->manifest->accepted_pdf_content);
        $this->assertNotSame($expected, $flight->manifest->accepted_pdf_content);
        $this->partialMock(ManifestPdfService::class, function ($mock): void {
            $mock->shouldNotReceive('render');
        });
        $flight->filedBy->pilotProfile()->create(['license_number' => 'CHANGED']);
        $flight->update(['status' => FlightPlanStatus::Cancelled]);
        $response = $this->actingAs($flight->filedBy)->get(route('flights.manifest.pdf', $flight))->assertOk();
        $this->assertSame($expected, $response->getContent());
    }

    public function test_failed_render_rolls_back_acceptance(): void
    {
        $flight = $this->completeFlight();
        $this->partialMock(ManifestPdfService::class, fn ($mock) => $mock->shouldReceive('render')->andThrow(new \RuntimeException('PDF unavailable')));
        try {
            app(FlightPlanMutationService::class)->accept($flight, $this->atmo(), 'AT');
            $this->fail('Acceptance should fail if the snapshot cannot be generated.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('PDF unavailable', $exception->getMessage());
        }
        $this->assertSame(FlightPlanStatus::Pending, $flight->fresh()->status);
        $this->assertNull($flight->fresh()->accepted_by_user_id);
        $this->assertNull($flight->manifest->accepted_pdf_content);
        $this->assertDatabaseCount('flight_plan_events', 0);
    }

    public function test_accepted_snapshot_cannot_be_overwritten(): void
    {
        $flight = $this->completeFlight();
        app(FlightPlanMutationService::class)->accept($flight, $this->atmo(), 'AT');
        $this->expectException(QueryException::class);
        DB::table('flight_manifests')->where('flight_id', $flight->id)->update(['accepted_pdf_sha256' => 'changed']);
    }

    public function test_failure_after_snapshot_creation_rolls_back_pdf_and_acceptance_together(): void
    {
        $flight = $this->completeFlight();
        DB::unprepared("CREATE TRIGGER fail_acceptance_event BEFORE INSERT ON flight_plan_events BEGIN SELECT RAISE(ABORT, 'Simulated acceptance event failure'); END");
        try {
            app(FlightPlanMutationService::class)->accept($flight, $this->atmo(), 'AT');
            $this->fail('Acceptance should roll back if recording the event fails.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('Simulated acceptance event failure', $exception->getMessage());
        } finally {
            DB::unprepared('DROP TRIGGER fail_acceptance_event');
        }
        $this->assertSame(FlightPlanStatus::Pending, $flight->fresh()->status);
        $this->assertNull($flight->manifest->accepted_pdf_content);
        $this->assertDatabaseCount('flight_plan_events', 0);
    }

    public function test_legacy_accepted_filing_is_not_silently_regenerated(): void
    {
        $flight = $this->completeFlight();
        $flight->forceFill(['status' => FlightPlanStatus::Accepted])->save();
        $this->actingAs($flight->filedBy)->get(route('flights.manifest.pdf', $flight))->assertStatus(409);
    }

    public function test_unsupported_glyphs_are_reported_without_silently_losing_names(): void
    {
        $data = $this->guestData(2);
        $data['persons'][1]['full_name'] = '山田太郎';
        $this->post(route('manifests.guest.pdf'), $data)->assertUnprocessable()->assertJsonPath('message', 'A manifest value contains characters unavailable in the PDF font. Use a supported spelling before generating the PDF.')->assertCookieMissing(config('session.cookie'));
        $this->assertDatabaseCount('manifest_persons', 0);
    }

    public function test_sample_command_generates_all_five_private_synthetic_pdfs(): void
    {
        Storage::fake('local');
        $this->artisan('manifest:pdf-samples')->assertSuccessful();
        foreach (['pic-only', 'three-passengers', 'full-25', 'continuation-30', 'long-names'] as $sample) {
            Storage::disk('local')->assertExists('manifest-samples/'.$sample.'.pdf');
        }
        $this->assertDatabaseCount('flights', 0);
    }

    private function completeFlight(): Flight
    {
        $flight = Flight::factory()->create();
        app(FlightManifestService::class)->save($flight, $flight->filedBy, [
            'aircraft_owner_name' => 'Owner', 'aircraft_owner_contact_number' => '123',
            'home_base_hangar' => 'RPUS', 'coa_expiry_date' => '2027-12-31', 'total_cargo_weight' => 0,
            'persons' => [['full_name' => $flight->pilot_in_command, 'nationality' => 'Filipino', 'classification' => 'pic']],
        ]);

        return $flight;
    }

    private function atmo(): User
    {
        return User::factory()->create(['role' => UserRole::Atmo, 'station' => 'RPUS', 'is_active' => true]);
    }

    /** @return array<string, mixed> */
    private function guestData(int $count = 1): array
    {
        return [
            'aircraft_identification' => 'RPCGUEST', 'type_of_aircraft' => 'C172', 'date_of_flight' => '2026-10-18', 'pilot_in_command' => 'Guest PIC',
            'persons' => array_map(fn (int $index): array => ['full_name' => $index === 1 ? 'Guest PIC' : 'Passenger '.str_pad((string) $index, 3, '0', STR_PAD_LEFT), 'nationality' => 'Filipino', 'classification' => $index === 1 ? 'pic' : 'passenger'], range(1, $count)),
        ];
    }

    private function encoded(string $text): string
    {
        return mb_convert_encoding($text, 'UTF-16BE', 'UTF-8');
    }

    private function streams(string $pdf): string
    {
        preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $matches);

        return implode("\n", array_map(fn (string $stream): string => @gzuncompress($stream) ?: $stream, $matches[1]));
    }
}
