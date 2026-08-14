<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'display_name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call([
            OperatorSeeder::class,
            ChuckLuatSeeder::class,
            DemoUsersSeeder::class,
            PilotProfileSeeder::class,
            StudentPilotSeeder::class,
            OperatorStaffSeeder::class,
            AtcProfileSeeder::class,
            AvsecProfileSeeder::class,
            RPUSFlightSeeder::class,
        ]);
    }
}
