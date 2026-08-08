<?php

namespace Database\Seeders;

use App\Models\AtcProfile;
use App\Models\User;
use Illuminate\Database\Seeder;

class AtcProfileSeeder extends Seeder
{
    public function run(): void
    {
        $atcUsers = User::whereIn('role', ['ATMO', 'ATSHQ'])->get();

        foreach ($atcUsers as $user) {
            if ($user->atcProfile()->exists()) {
                continue;
            }

            AtcProfile::create([
                'user_id' => $user->id,
                'wiresign' => $user->wiresign,
                'facility' => 'RPUS TWR',
                'position' => 'Controller',
                'endorsements' => 'IFR, VFR',
                'remarks' => 'Seeded ATC profile.',
            ]);
        }
    }
}
