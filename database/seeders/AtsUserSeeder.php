<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Operator;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AtsUserSeeder extends Seeder
{
    public function run(): void
    {
        $operator = Operator::first();
        $password = 'DemoUser!23';

        $user = User::firstOrCreate(
            ['email' => 'ats.hq@echo.example.com'],
            [
                'name' => 'ATS Headquarters',
                'first_name' => 'ATS',
                'middle_name' => null,
                'last_name' => 'Headquarters',
                'display_name' => 'ATS Headquarters',
                'username' => 'ats.hq@echo.example.com',
                'employee_id' => 'ATSHQ-1001',
                'wiresign' => 'HQ',
                'role' => UserRole::AtsHq,
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
