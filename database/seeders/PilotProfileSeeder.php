<?php

namespace Database\Seeders;

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

            PilotProfile::create([
                'user_id' => $user->id,
                'license_number' => 'PILOT-'.fake()->numerify('#####'),
                'ratings' => 'IR ME',
                'license_expiry_date' => now()->addYears(2)->toDateString(),
                'medical_expiry_date' => now()->addYear()->toDateString(),
                'home_base' => $user->station ?: 'RPUS',
                'remarks' => 'Seeded pilot profile.',
            ]);
        }
    }
}
