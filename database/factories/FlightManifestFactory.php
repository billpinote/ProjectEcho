<?php

namespace Database\Factories;

use App\Models\Flight;
use App\Models\FlightManifest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FlightManifest> */
class FlightManifestFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'flight_id' => Flight::factory(),
            'created_by_user_id' => User::factory(),
            'aircraft_owner_name' => fake()->company(),
            'aircraft_owner_contact_number' => fake()->phoneNumber(),
            'home_base_hangar' => 'RPUS',
            'coa_expiry_date' => now()->addYear()->toDateString(),
            'total_cargo_weight' => 0,
        ];
    }
}
