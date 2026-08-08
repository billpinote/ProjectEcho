<?php

namespace Database\Factories;

use App\Models\AtcProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AtcProfile>
 */
class AtcProfileFactory extends Factory
{
    protected $model = AtcProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'wiresign' => fake()->bothify('ATC-###'),
            'facility' => fake()->randomElement(['RPUS TWR', 'RPLL ACC', 'RPVM APP']),
            'position' => fake()->randomElement(['SUPERVISOR', 'CONTROLLER', 'APPROACH']),
            'endorsements' => fake()->optional()->sentence(),
            'remarks' => fake()->optional()->sentence(),
        ];
    }
}
