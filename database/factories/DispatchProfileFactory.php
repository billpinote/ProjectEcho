<?php

namespace Database\Factories;

use App\Models\DispatchProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DispatchProfile>
 */
class DispatchProfileFactory extends Factory
{
    protected $model = DispatchProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'dispatcher_license_number' => fake()->bothify('DISP-#####'),
            'dispatcher_certificate' => fake()->bothify('CERT-#####'),
            'department' => fake()->randomElement(['Operations', 'Flight Planning', 'Ground Handling']),
            'position' => fake()->randomElement(['Dispatcher', 'Supervisor', 'Coordinator']),
            'office_phone' => fake()->phoneNumber(),
            'mobile_number' => fake()->phoneNumber(),
            'shift' => fake()->randomElement(['Morning', 'Afternoon', 'Night']),
            'remarks' => fake()->optional()->sentence(),
        ];
    }
}
