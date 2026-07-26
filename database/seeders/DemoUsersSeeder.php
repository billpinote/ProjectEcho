<?php

namespace Database\Seeders;

use App\Models\Operator;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        $operator = Operator::first();

        $password = 'DemoUser!23';

        $users = [
            [
                'name' => 'Miguel Santos',
                'first_name' => 'Miguel',
                'middle_name' => 'A.',
                'last_name' => 'Santos',
                'display_name' => 'Miguel Santos',
                'email' => 'miguel.santos@atc.example.com',
                'username' => 'miguel.santos@atc.example.com',
                'employee_id' => 'ATC-1001',
                'wiresign' => 'MS',
                'role' => 'ATMO',
                'station' => 'RPUS',
                'operator_id' => $operator?->id,
            ],
            [
                'name' => 'Ava Mendoza',
                'first_name' => 'Ava',
                'middle_name' => 'L.',
                'last_name' => 'Mendoza',
                'display_name' => 'Ava Mendoza',
                'email' => 'ava.mendoza@dispatch.example.com',
                'username' => 'ava.mendoza@dispatch.example.com',
                'employee_id' => 'DISP-1001',
                'wiresign' => null,
                'role' => 'DISPATCH',
                'station' => 'RPUS',
                'operator_id' => $operator?->id,
            ],
            [
                'name' => 'Rico Del Rosario',
                'first_name' => 'Rico',
                'middle_name' => 'C.',
                'last_name' => 'Del Rosario',
                'display_name' => 'Rico Del Rosario',
                'email' => 'rico.delrosario@avsec.example.com',
                'username' => 'rico.delrosario@avsec.example.com',
                'employee_id' => 'AVSEC-1001',
                'wiresign' => null,
                'role' => 'AVSEC',
                'station' => 'RPUS',
                'operator_id' => $operator?->id,
            ],
        ];

        foreach ($users as $userData) {
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                array_merge($userData, [
                    'password' => Hash::make($password),
                    'is_active' => true,
                ]),
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
}
