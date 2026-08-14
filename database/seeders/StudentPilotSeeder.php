<?php

namespace Database\Seeders;

use App\Domain\Pilots\Enums\PilotLicenseType;
use App\Domain\Pilots\Enums\PilotQualificationCategory;
use App\Domain\Users\Enums\UserRole;
use App\Models\Operator;
use App\Models\PilotProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StudentPilotSeeder extends Seeder
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

        $pilot = User::query()->updateOrCreate(
            ['email' => 'juan.delacruz@example.com'],
            [
                'name' => 'Juan Dela Cruz',
                'first_name' => 'Juan',
                'middle_name' => null,
                'last_name' => 'Dela Cruz',
                'display_name' => 'Juan Dela Cruz',
                'username' => 'juan.delacruz',
                'operator_id' => $operator->id,
                'role' => UserRole::Pilot,
                'is_active' => true,
                'password' => Hash::make($password),
            ],
        );

        $pilot->authAccounts()->updateOrCreate(
            ['provider' => 'password'],
            [
                'identifier' => $pilot->email,
                'email' => $pilot->email,
                'password_hash' => Hash::make($password),
                'email_verified_at' => now(),
            ],
        );

        $profile = PilotProfile::query()->updateOrCreate(
            ['user_id' => $pilot->id],
            [
                'user_id' => $pilot->id,
                'license_type' => PilotLicenseType::StudentPilot,
                'license_number' => 'SPL-DEV-001',
                'license_expiry_date' => '2028-12-31',
                'medical_expiry_date' => '2027-12-31',
                'operator' => null,
                'remarks' => 'Fictional development student pilot profile for non-PIC preparer workflow testing.',
            ],
        );

        $qualifications = [
            [
                'category' => PilotQualificationCategory::Endorsement->value,
                'code' => 'SPL',
                'description' => 'Student Pilot Authorization',
                'expiry_date' => '2027-12-31',
                'remarks' => 'Development-only SPL qualification marker.',
            ],
            [
                'category' => PilotQualificationCategory::AircraftRating->value,
                'code' => 'C172-STUDENT',
                'description' => 'Cessna 172 student training authorization',
                'expiry_date' => '2027-12-31',
                'remarks' => 'Development-only training qualification.',
            ],
        ];

        foreach ($qualifications as $qualification) {
            $profile->qualifications()->updateOrCreate(
                [
                    'category' => $qualification['category'],
                    'code' => $qualification['code'],
                ],
                $qualification,
            );
        }

        $expectedKeys = collect($qualifications)
            ->map(fn (array $qualification): string => $qualification['category'].'|'.$qualification['code'])
            ->all();

        $profile->qualifications()
            ->get()
            ->reject(fn ($qualification): bool => in_array($qualification->category->value.'|'.$qualification->code, $expectedKeys, true))
            ->each
            ->delete();
    }
}
