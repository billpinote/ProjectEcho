<?php

namespace Database\Seeders;

use App\Models\AuthAccount;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class JhovelPinoteSeeder extends Seeder
{
    public function run(): void
    {
        $password = 'Rpc8249*';

        $user = User::create([
            'name' => 'Jhovel Pinote',
            'first_name' => 'Jhovel',
            'middle_name' => 'Cenabre',
            'last_name' => 'Pinote',
            'suffix' => null,
            'display_name' => 'Jhovel Pinote',
            'email' => 'bill.pinote@outlook.com',
            'username' => 'bill.pinote@outlook.com',
            'employee_id' => '94451',
            'wiresign' => 'JH',
            'password' => Hash::make($password),
            'role' => 'ATMO',
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
