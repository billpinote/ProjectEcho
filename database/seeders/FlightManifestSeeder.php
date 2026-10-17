<?php

namespace Database\Seeders;

use App\Domain\FlightPlans\Services\FlightManifestService;
use App\Models\Flight;
use App\Models\FlightManifest;
use Illuminate\Database\Seeder;

class FlightManifestSeeder extends Seeder
{
    public function run(): void
    {
        Flight::query()->whereDoesntHave('manifest')->with('filedBy')->get()
            ->each(function (Flight $flight): void {
                $actor = $flight->filedBy;
                if ($actor !== null && $actor->can('create', [FlightManifest::class, $flight])) {
                    app(FlightManifestService::class)->save($flight, $actor, []);
                }
            });
    }
}
