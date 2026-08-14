<?php

namespace Database\Seeders;

use App\Domain\Users\Enums\UserRole;
use App\Models\Operator;
use App\Models\OperatorStaffProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class OperatorStaffSeeder extends Seeder
{
    public function run(): void
    {
        $operator = Operator::query()->firstOrCreate(
            ['name' => 'Alpha Aviation Group'],
            [
                'short_name' => 'AAG',
                'is_active' => true,
                'remarks' => 'Fictional development operator.',
            ],
        );

        $password = 'password';

        $user = User::query()->updateOrCreate(
            ['email' => 'pedro.santos@example.com'],
            [
                'name' => 'Pedro Santos',
                'first_name' => 'Pedro',
                'middle_name' => null,
                'last_name' => 'Santos',
                'display_name' => 'Pedro Santos',
                'username' => 'pedro.santos',
                'operator_id' => $operator->id,
                'role' => UserRole::OperatorStaff,
                'is_active' => true,
                'password' => Hash::make($password),
            ],
        );

        $user->authAccounts()->updateOrCreate(
            ['provider' => 'password'],
            [
                'identifier' => $user->email,
                'email' => $user->email,
                'password_hash' => Hash::make($password),
                'email_verified_at' => now(),
            ],
        );

        OperatorStaffProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'user_id' => $user->id,
                'operator_id' => $operator->id,
                'position_title' => 'Aircraft Mechanic',
                'company_employee_id' => 'AAG-MECH-001',
                'authorization_reference' => 'AAG-FPL-REP-001',
                'authorization_expiry_date' => '2028-12-31',
                'is_authorized' => true,
                'remarks' => 'Authorized operator representative for flight plan preparation and filing.',
            ],
        );
    }
}
