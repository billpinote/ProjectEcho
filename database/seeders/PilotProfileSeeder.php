<?php

namespace Database\Seeders;

use App\Domain\Pilots\Enums\PilotLicenseType;
use App\Domain\Pilots\Enums\PilotQualificationCategory;
use App\Models\PilotProfile;
use App\Models\User;
use Illuminate\Database\Seeder;

class PilotProfileSeeder extends Seeder
{
    public function run(): void
    {
        $pilotUsers = User::where('role', 'PILOT')->get();

        foreach ($pilotUsers as $user) {
            if ($user->pilotProfile()->exists()) {
                continue;
            }

            $profile = PilotProfile::create([
                'user_id' => $user->id,
                'license_type' => fake()->randomElement(PilotLicenseType::cases())->value,
                'license_number' => 'PILOT-'.fake()->numerify('#####'),
                'ratings' => 'IR ME',
                'license_expiry_date' => now()->addYears(2)->toDateString(),
                'medical_expiry_date' => now()->addYear()->toDateString(),
                'operator' => $user->station ?: 'RPUS',
                'remarks' => 'Seeded pilot profile.',
            ]);

            $profile->qualifications()->createMany([
                [
                    'category' => PilotQualificationCategory::InstrumentRating->value,
                    'code' => 'IR',
                    'description' => 'Instrument rating',
                    'expiry_date' => now()->addYears(2)->toDateString(),
                ],
                [
                    'category' => PilotQualificationCategory::AircraftRating->value,
                    'code' => 'C172',
                    'description' => 'Cessna 172 aircraft rating',
                    'expiry_date' => null,
                ],
            ]);
        }
    }
}
