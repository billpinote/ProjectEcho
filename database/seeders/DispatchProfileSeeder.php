<?php

namespace Database\Seeders;

use App\Models\DispatchProfile;
use App\Models\User;
use Illuminate\Database\Seeder;

class DispatchProfileSeeder extends Seeder
{
    public function run(): void
    {
        $dispatchUsers = User::where('role', 'DISPATCH')->get();

        foreach ($dispatchUsers as $user) {
            if ($user->dispatchProfile()->exists()) {
                continue;
            }

            DispatchProfile::create([
                'user_id' => $user->id,
                'dispatcher_license_number' => 'DISP-'.fake()->numerify('#####'),
                'dispatcher_certificate' => 'CERT-'.fake()->numerify('#####'),
                'department' => 'Operations',
                'position' => 'Dispatcher',
                'office_phone' => fake()->phoneNumber(),
                'mobile_number' => fake()->phoneNumber(),
                'shift' => 'Morning',
                'remarks' => 'Seeded dispatch profile.',
            ]);
        }
    }
}
