<?php

namespace Database\Seeders;

use App\Models\AvsecProfile;
use App\Models\User;
use Illuminate\Database\Seeder;

class AvsecProfileSeeder extends Seeder
{
    public function run(): void
    {
        $avsecUsers = User::where('role', 'AVSEC')->get();

        foreach ($avsecUsers as $user) {
            if ($user->avsecProfile()->exists()) {
                continue;
            }

            AvsecProfile::create([
                'user_id' => $user->id,
                'security_certification' => 'SEC-'.fake()->numerify('###'),
                'certification_expiry' => now()->addYear()->toDateString(),
                'security_clearance_level' => 'Level 2',
                'position' => 'Security Officer',
                'remarks' => 'Seeded AVSEC profile.',
            ]);
        }
    }
}
