<?php

namespace Database\Factories;

use App\Models\AuthAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AuthAccount>
 */
class AuthAccountFactory extends Factory
{
    protected $model = AuthAccount::class;

    public function definition(): array
    {
        $password = Hash::make('password');

        return [
            'user_id' => User::factory(),
            'provider' => 'password',
            'identifier' => fake()->unique()->safeEmail(),
            'password_hash' => $password,
            'provider_user_id' => null,
            'email' => fake()->safeEmail(),
            'email_verified_at' => now(),
            'last_login_at' => null,
            'last_login_ip' => null,
        ];
    }

    public function password(string $password): static
    {
        return $this->state(fn (array $attributes) => [
            'password_hash' => Hash::make($password),
        ]);
    }
}
