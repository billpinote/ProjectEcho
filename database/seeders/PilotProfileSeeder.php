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

class PilotProfileSeeder extends Seeder
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

        $pilot = User::query()->updateOrCreate(
            ['email' => 'pilot@example.com'],
            [
                'name' => 'Jesse James Vita Superales',
                'first_name' => 'Jesse James',
                'middle_name' => 'Vita',
                'last_name' => 'Superales',
                'display_name' => 'Jesse James Vita Superales',
                'username' => 'pilot',
                'operator_id' => $operator->id,
                'role' => UserRole::Pilot,
                'is_active' => true,
                'password' => Hash::make('password'),
            ],
        );

        $pilot->authAccounts()->updateOrCreate(
            ['provider' => 'password'],
            [
                'identifier' => $pilot->email,
                'email' => $pilot->email,
                'password_hash' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        $profile = PilotProfile::query()->updateOrCreate(
            ['user_id' => $pilot->id],
            [
                'user_id' => $pilot->id,
                'license_type' => PilotLicenseType::CommercialPilot,
                'license_number' => '987654',
                'license_expiry_date' => '2028-12-31',
                'medical_expiry_date' => '2027-12-31',
                'operator' => null,
                'remarks' => 'Fictional development pilot profile.',
            ],
        );

        $qualifications = [
            [
                'category' => PilotQualificationCategory::AircraftRating->value,
                'code' => 'C172',
                'description' => 'Cessna 172',
                'expiry_date' => '2027-06-30',
            ],
            [
                'category' => PilotQualificationCategory::AircraftRating->value,
                'code' => 'C208',
                'description' => 'Cessna 208',
                'expiry_date' => '2027-09-30',
            ],
            [
                'category' => PilotQualificationCategory::InstrumentRating->value,
                'code' => 'IR',
                'description' => 'Instrument Rating',
                'expiry_date' => '2027-12-31',
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
