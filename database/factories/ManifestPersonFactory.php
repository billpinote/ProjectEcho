<?php

namespace Database\Factories;

use App\Models\FlightManifest;
use App\Models\ManifestPerson;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ManifestPerson> */
class ManifestPersonFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'flight_manifest_id' => FlightManifest::factory(),
            'sequence_number' => 1,
            'full_name' => fake()->name(),
            'nationality' => 'Filipino',
            'classification' => 'passenger',
        ];
    }
}
