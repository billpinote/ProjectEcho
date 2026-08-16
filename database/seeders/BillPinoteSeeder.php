<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class BillPinoteSeeder extends Seeder
{
    public function run(): void
    {
        $password = 'Rpc8249*';

        $user = User::query()->updateOrCreate(
            ['email' => 'bill.pinote@gmail.com'],
            [
                'name' => 'Bill Pinote',
                'first_name' => 'Bill',
                'middle_name' => 'Cenabre',
                'last_name' => 'Pinote',
                'suffix' => null,
                'display_name' => 'Bill Pinote',
                'email' => 'bill.pinote@gmail.com',
                'username' => 'bill.pinote@gmail.com',
                'employee_id' => '9445',
                'wiresign' => 'PI',
                'password' => Hash::make($password),
                'role' => 'ARTISAN',
                'station' => 'RPUS',
                'is_active' => true,
            ],
        );

        $user->authAccounts()->updateOrCreate(
            ['provider' => 'password'],
            [
                'identifier' => $user->email,
                'password_hash' => Hash::make($password),
                'email' => $user->email,
                'email_verified_at' => now(),
            ],
        );
    }
}
