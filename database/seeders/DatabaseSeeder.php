<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            OperatorSeeder::class,
            AdminUserSeeder::class,
            AtsUserSeeder::class,
            BillPinoteSeeder::class,
            ChuckLuatSeeder::class,
            JhovelPinoteSeeder::class,
            DemoUsersSeeder::class,
            PilotProfileSeeder::class,
            StudentPilotSeeder::class,
            OperatorStaffSeeder::class,
            AtcProfileSeeder::class,
            AvsecProfileSeeder::class,
            DispatchProfileSeeder::class,
            PendingFlightPlansSeeder::class,
            RPUSFlightSeeder::class,
        ]);
    }
}
