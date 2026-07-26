<?php

namespace Database\Factories;

use App\Models\AvsecProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AvsecProfile>
 */
class AvsecProfileFactory extends Factory
{
    protected $model = AvsecProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'security_certification' => fake()->randomElement(['SEC-01', 'SEC-02', 'SEC-03']),
            'certification_expiry' => fake()->dateTimeBetween('now', '+2 years')->format('Y-m-d'),
            'security_clearance_level' => fake()->randomElement(['Level 1', 'Level 2', 'Level 3']),
            'position' => fake()->randomElement(['Security Officer', 'Inspector', 'Supervisor']),
            'remarks' => fake()->optional()->sentence(),
        ];
    }
}
