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
        if (config('echo-demo.enabled')) {
            $this->call(EchoDemoSeeder::class);
        }

        // Legacy demo account seeders remain available explicitly; the opt-in
        // EchoDemoSeeder is the single demo account entrypoint for this pipeline.
        $this->call([
            OperatorSeeder::class,
            AtsUserSeeder::class,
            BillPinoteSeeder::class,
            ChuckLuatSeeder::class,
            JhovelPinoteSeeder::class,
            AtcProfileSeeder::class,
            AvsecProfileSeeder::class,
            DispatchProfileSeeder::class,
            PendingFlightPlansSeeder::class,
            RPUSFlightSeeder::class,
        ]);
    }
}
