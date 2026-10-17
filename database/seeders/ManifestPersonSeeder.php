<?php

namespace Database\Seeders;

use App\Models\FlightManifest;
use Illuminate\Database\Seeder;

class ManifestPersonSeeder extends Seeder
{
    public function run(): void
    {
        FlightManifest::query()->whereDoesntHave('persons')->with('flight')->get()
            ->each(function (FlightManifest $manifest): void {
                if (! $manifest->flight->hasBeenAccepted() && filled($manifest->flight->pilot_in_command)) {
                    $manifest->persons()->create([
                        'sequence_number' => 1,
                        'full_name' => $manifest->flight->pilot_in_command,
                        'classification' => 'pic',
                    ]);
                }
            });
    }
}
