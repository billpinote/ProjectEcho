<?php

namespace Database\Factories;

use App\Domain\Pilots\Enums\PilotQualificationCategory;
use App\Models\PilotQualification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PilotQualification>
 */
class PilotQualificationFactory extends Factory
{
    protected $model = PilotQualification::class;

    public function definition(): array
    {
        return [
            'pilot_profile_id' => null,
            'category' => fake()->randomElement(PilotQualificationCategory::cases())->value,
            'code' => fake()->randomElement(['C172', 'C208', 'IR', 'FI']),
            'description' => fake()->optional()->sentence(3),
            'expiry_date' => fake()->optional()->dateTimeBetween('now', '+3 years')?->format('Y-m-d'),
            'remarks' => fake()->optional()->sentence(),
        ];
    }
}
