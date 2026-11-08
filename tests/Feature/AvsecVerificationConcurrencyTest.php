<?php

namespace Tests\Feature;

use App\Domain\FlightPlans\Services\FlightManifestService;
use App\Domain\FlightPlans\Services\FlightPlanPdfService;
use App\Domain\FlightPlans\Services\FlightPlanQrPayloadService;
use App\Domain\Users\Enums\UserRole;
use App\Models\Flight;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Support\SignsFlightDocuments;
use Tests\TestCase;

class AvsecVerificationConcurrencyTest extends TestCase
{
    use SignsFlightDocuments;

    #[DataProvider('races')]
    public function test_concurrent_confirmation_and_content_writes_cannot_both_succeed(string $secondOperation): void
    {
        $database = tempnam(sys_get_temp_dir(), 'echo-avsec-');
        $original = config('database.connections.sqlite');
        $processes = [];
        try {
            config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $database]);
            DB::purge('sqlite');
            // Only this newly created, isolated database is migrated.
            $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true])->assertSuccessful();
            $flight = Flight::factory()->create()->refresh();
            app(FlightManifestService::class)->save($flight, $flight->filedBy, [
                'aircraft_owner_name' => 'Owner', 'aircraft_owner_contact_number' => '1234567',
                'home_base_hangar' => 'RPUS', 'coa_expiry_date' => now()->addYear()->toDateString(),
                'total_cargo_weight' => 0,
                'persons' => [['full_name' => $flight->pilot_in_command, 'nationality' => 'PH', 'classification' => 'pic']],
            ]);
            $this->signFlightDocuments($flight);
            app(FlightPlanPdfService::class)->regenerate($flight);
            $officer = User::factory()->create(['role' => UserRole::Avsec, 'station' => 'RPUS']);
            $payload = app(FlightPlanQrPayloadService::class)->buildPayload($flight->fresh());
            $script = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $argv[1], 'filesystems.disks.local.root' => $argv[2]]);
Illuminate\Support\Facades\DB::purge('sqlite');
Illuminate\Support\Facades\DB::statement('PRAGMA busy_timeout = 5000');
file_put_contents($argv[1].'.ready'.$argv[6], 'ready');
while (!file_exists($argv[1].'.go')) { usleep(10000); }
try {
    if ($argv[5] === 'confirm') {
        app(App\Domain\FlightPlans\Services\AvsecVerificationService::class)->confirm($argv[3], App\Models\User::findOrFail($argv[4]), true);
    } else {
        App\Models\Flight::findOrFail($argv[7])->update(['route' => 'CONCURRENT CORRECTION']);
    }
    echo 'succeeded';
} catch (Illuminate\Validation\ValidationException|Illuminate\Database\QueryException|Illuminate\Database\DeadlockException $exception) {
    echo 'blocked';
}
PHP;
            foreach (['confirm', $secondOperation] as $index => $operation) {
                $process = new Process([PHP_BINARY, '-d', 'memory_limit=256M', '-r', $script,
                    $database, Storage::disk('local')->path(''), $payload, (string) $officer->id, $operation, (string) $index, (string) $flight->id,
                ], base_path(), ['APP_ENV' => 'testing', 'APP_KEY' => config('app.key')], timeout: 45);
                $process->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 20;
            while ((! file_exists($database.'.ready0') || ! file_exists($database.'.ready1')) && microtime(true) < $deadline) {
                usleep(10000);
            }
            $this->assertFileExists($database.'.ready0');
            $this->assertFileExists($database.'.ready1');
            file_put_contents($database.'.go', 'go');
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
            }
            $results = array_map(fn (Process $process) => $process->getOutput(), $processes);
            $this->assertEqualsCanonicalizing(['succeeded', 'blocked'], $results);
            $flight->refresh();
            $this->assertLessThanOrEqual(1, $flight->avsecVerification()->count());
            if ($flight->isAvsecFrozen()) {
                $this->assertSame('DCT', $flight->route);
                $this->assertSame(1, $flight->events()->where('event_type', 'avsec_verified')->count());
            } else {
                $this->assertSame('CONCURRENT CORRECTION', $flight->route);
            }
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            DB::purge('sqlite');
            config(['database.connections.sqlite' => $original]);
            foreach ([$database, $database.'.ready0', $database.'.ready1', $database.'.go', $database.'-journal'] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }

    public static function races(): array
    {
        return [['confirm'], ['mutate']];
    }
}
