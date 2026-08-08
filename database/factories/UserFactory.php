<?php

namespace Database\Factories;

use App\Domain\Users\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->name();

        return [
            'name' => $name,
            'display_name' => $name,
            'first_name' => fake()->firstName(),
            'middle_name' => fake()->optional()->firstName(),
            'last_name' => fake()->lastName(),
            'suffix' => fake()->optional()->randomElement(['Jr', 'Sr', 'III', null]),
            'email' => fake()->unique()->safeEmail(),
            'username' => fake()->unique()->userName(),
            'employee_id' => fake()->unique()->bothify('EMP-####'),
            'wiresign' => fake()->bothify('ATC-###'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::Atmo,
            'station' => fake()->randomElement(['RPUS', 'RPLL', 'RPVM', 'RPVD', 'RPLC']),
            'operator_id' => null,
            'is_active' => true,
            'last_login_at' => null,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
