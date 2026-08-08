<?php

namespace Database\Factories;

use App\Models\PilotProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PilotProfile>
 */
class PilotProfileFactory extends Factory
{
    protected $model = PilotProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'license_number' => fake()->bothify('PILOT-#####'),
            'ratings' => fake()->randomElement(['IR', 'ME', 'ATPL', 'CPL', 'SEL', 'MCC']),
            'license_expiry_date' => fake()->dateTimeBetween('now', '+3 years')->format('Y-m-d'),
            'medical_expiry_date' => fake()->dateTimeBetween('now', '+2 years')->format('Y-m-d'),
            'operator' => fake()->randomElement(['RPUS', 'RPLL', 'RPVM', 'RPVD', 'RPLC']),
            'remarks' => fake()->optional()->sentence(),
        ];
    }
}
