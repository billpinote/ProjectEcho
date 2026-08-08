<?php

namespace Database\Seeders;

use App\Models\AuthAccount;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ChuckLuatSeeder extends Seeder
{
    public function run(): void
    {
        $password = 'ChuckLuat!24';

        $user = User::create([
            'name' => 'Chuck Luat',
            'first_name' => 'Chuck',
            'middle_name' => 'N.',
            'last_name' => 'Luat',
            'suffix' => null,
            'display_name' => 'Chuck Luat',
            'email' => 'chuck.luat@example.com',
            'username' => 'chuck.luat@example.com',
            'employee_id' => '95123',
            'wiresign' => 'CL',
            'password' => Hash::make($password),
            'role' => 'PILOT',
            'station' => 'RPUS',
            'is_active' => true,
        ]);

        AuthAccount::create([
            'user_id' => $user->id,
            'provider' => 'password',
            'identifier' => $user->email,
            'password_hash' => Hash::make($password),
            'email' => $user->email,
            'email_verified_at' => now(),
        ]);
    }
}
