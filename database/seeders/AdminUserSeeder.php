<?php

namespace Database\Seeders;

use App\Domain\Users\Enums\UserRole;
use App\Models\Operator;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $operator = Operator::first();
        $password = 'DemoUser!23';

        $user = User::firstOrCreate(
            ['email' => 'admin@echo.example.com'],
            [
                'name' => 'Echo Admin',
                'first_name' => 'Echo',
                'middle_name' => null,
                'last_name' => 'Admin',
                'display_name' => 'Echo Admin',
                'username' => 'admin@echo.example.com',
                'employee_id' => 'ADMIN-1001',
                'wiresign' => null,
                'role' => UserRole::Admin,
                'station' => 'RPUS',
                'operator_id' => $operator?->id,
                'password' => Hash::make($password),
                'is_active' => true,
            ],
        );

        if (! $user->authAccounts()->exists()) {
            $user->authAccounts()->create([
                'provider' => 'password',
                'identifier' => $user->email,
                'password_hash' => Hash::make($password),
                'email' => $user->email,
                'email_verified_at' => now(),
            ]);
        }
    }
}
