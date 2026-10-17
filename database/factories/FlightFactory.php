<?php

namespace Database\Factories;

use App\Domain\FlightPlans\Enums\FlightPlanStatus;
use App\Domain\Users\Enums\UserRole;
use App\Models\Flight;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Flight> */
class FlightFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'filed_by_user_id' => User::factory()->state(['role' => UserRole::Pilot]),
            'status' => FlightPlanStatus::Pending,
            'date_of_flight' => now('Asia/Manila')->addDay()->toDateString(),
            'proposed_time' => '1430',
            'aircraft_identification' => 'RPC123',
            'type_of_aircraft' => 'C172',
            'departure_aerodrome' => 'RPUS',
            'destination_aerodrome' => 'RPLL',
            'route' => 'DCT',
            'pilot_in_command' => fake()->name(),
            'pilot_license_no' => 'PPL-12345',
            'pilot_ratings' => 'SEL',
            'license_expiry_date' => now()->addYear()->toDateString(),
            'persons_on_board' => 1,
        ];
    }
}
