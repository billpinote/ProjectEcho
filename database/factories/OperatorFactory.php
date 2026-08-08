<?php

namespace Database\Factories;

use App\Models\Operator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Operator>
 */
class OperatorFactory extends Factory
{
    protected $model = Operator::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'short_name' => fake()->unique()->bothify('OP###'),
            'icao_code' => fake()->unique()->bothify('R###'),
            'certificate_number' => fake()->bothify('CERT-####'),
            'address' => fake()->address(),
            'contact_number' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'remarks' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
