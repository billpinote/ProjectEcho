<?php

namespace Database\Seeders;

use App\Enums\FlightPlanStatus;
use App\Models\Flight;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class RPUSFlightSeeder extends Seeder
{
    public function run(): void
    {
        $today = Carbon::now('Asia/Manila')->startOfDay();
        $aircraftTypes = ['C172', 'SIRA', 'C152'];
        $destinations = ['RPUQ', 'RPLB', 'RPLC', 'RPUI', 'RPUR'];
        $trainingAreas = ['BACNOTAN', 'CANDON', 'LUNA', 'TAGUDIN', 'SANTA MARIA', 'NAGUILIAN', 'PATTERN'];
        $times = ['2300', '2330', '0000', '0030', '0100', '0130', '0200', '0230', '0300', '0400', '0500', '0600'];

        $pilotNames = [
            'CAPTAIN SANTOS',
            'CADET MORALES',
            'INSTRUCTOR RAMOS',
            'PILOT GONZALES',
            'CAPTAIN CRUZ',
            'PILOT FERNANDEZ',
            'CADET PAREDES',
            'INSTRUCTOR RIVERA',
            'CAPTAIN TORRES',
            'PILOT MENDOZA',
            'CADET GARCIA',
            'INSTRUCTOR RODRIGUEZ'
        ];

        $flights = [];
        $usedCallsigns = [];

        // Create 8 flights to international destinations
        for ($i = 0; $i < 8; $i++) {
            $destination = $destinations[$i % count($destinations)];
            $aircraftType = $aircraftTypes[$i % count($aircraftTypes)];
            $route = $this->generateRoute($destination);
            $eet = $this->calculateEET($destination);
            $callsign = $this->generateRandomCallsign($usedCallsigns);

            $flights[] = [
                'aircraft_identification' => $callsign,
                'type_of_aircraft' => $aircraftType,
                'departure_aerodrome' => 'RPUS',
                'destination_aerodrome' => $destination,
                'proposed_time' => $times[$i % count($times)],
                'total_eet' => $eet,
                'endurance' => $this->calculateEndurance($aircraftType),
                'route' => $route,
                'pilot_in_command' => $pilotNames[$i],
                'type_of_flight' => 'G',
                'persons_on_board' => 2,
            ];
        }

        // Create 4 local training flights (RPUS to RPUS)
        for ($i = 8; $i < 12; $i++) {
            $trainingArea = $trainingAreas[($i - 8) % count($trainingAreas)];
            $aircraftType = $aircraftTypes[$i % count($aircraftTypes)];
            $route = 'RPUS ' . $trainingArea . ' RPUS';
            $callsign = $this->generateRandomCallsign($usedCallsigns);

            $flights[] = [
                'aircraft_identification' => $callsign,
                'type_of_aircraft' => $aircraftType,
                'departure_aerodrome' => 'RPUS',
                'destination_aerodrome' => 'RPUS',
                'proposed_time' => $times[$i % count($times)],
                'total_eet' => '0200',
                'endurance' => $this->calculateEndurance($aircraftType),
                'route' => $route,
                'pilot_in_command' => $pilotNames[$i],
                'type_of_flight' => 'T',
                'persons_on_board' => 2,
                'training_area' => $trainingArea,
            ];
        }

        $dateOfFlight = $today->toDateString();

        foreach ($flights as $flight) {
            Flight::query()->updateOrCreate(
                [
                    'aircraft_identification' => $flight['aircraft_identification'],
                    'date_of_flight' => $dateOfFlight,
                ],
                [
                    'addressees' => 'RPLLYJYX',
                    'originator' => 'RPUSYFYX',
                    'date_of_filing' => $today->toDateString(),
                    'date_of_flight' => $dateOfFlight,
                    'aircraft_identification' => $flight['aircraft_identification'],
                    'flight_rules' => 'V',
                    'type_of_flight' => $flight['type_of_flight'],
                    'number' => '1',
                    'type_of_aircraft' => $flight['type_of_aircraft'],
                    'wake_turbulence_cat' => 'L',
                    'equipment_10a' => 'SDFGIRY',
                    'equipment_10b' => 'S',
                    'departure_aerodrome' => $flight['departure_aerodrome'],
                    'proposed_time' => $flight['proposed_time'],
                    'cruising_speed' => 'N0100',
                    'level' => 'A025',
                    'route' => $flight['route'],
                    'destination_aerodrome' => $flight['destination_aerodrome'],
                    'total_eet' => $flight['total_eet'],
                    'altn_aerodrome_1' => 'RPVK',
                    'altn_aerodrome_2' => 'RPMZ',
                    'other_information' => 'DOF/'.str_replace('-', '', $dateOfFlight).' RMK/SEEDED RPUS FLIGHT',
                    'other_info_dof' => $dateOfFlight,
                    'other_info_rmk' => 'SEEDED RPUS FLIGHT',
                    'endurance' => $flight['endurance'],
                    'persons_on_board' => $flight['persons_on_board'],
                    'aircraft_colour_and_markings' => 'WHITE RED',
                    'remarks' => 'AUTO SEEDED FOR TESTING - ' . ($flight['destination_aerodrome'] === 'RPUS' ? 'LOCAL TRAINING' : 'INTERNATIONAL ROUTE'),
                    'pilot_in_command' => $flight['pilot_in_command'],
                    'filed_by_name' => $flight['pilot_in_command'],
                    'filed_by_signature' => $flight['pilot_in_command'],
                    'pilot_license_no' => 'LIC-' . substr($flight['aircraft_identification'], -4),
                    'pilot_ratings' => 'IR ME',
                    'license_expiry_date' => $today->copy()->addYear()->toDateString(),
                    'status' => FlightPlanStatus::Pending,
                    'accepted_by_user_id' => null,
                    'accepted_by_wiresign' => null,
                    'rejected_by_wiresign' => null,
                    'rejection_reason' => null,
                    'reviewed_at' => null,
                ],
            );
        }
    }

    private function generateRandomCallsign(array &$usedCallsigns): string
    {
        do {
            $digitCount = random_int(2, 4);
            $number = (string) random_int(10 ** ($digitCount - 1), (10 ** $digitCount) - 1);
            $callsign = 'RPC' . $number;
        } while (in_array($callsign, $usedCallsigns, true));

        $usedCallsigns[] = $callsign;

        return $callsign;
    }

    private function generateRoute(string $destination): string
    {
        $routes = [
            'RPUQ' => 'RPUS RPUQ',
            'RPLB' => 'RPUS RPLB',
            'RPLC' => 'RPUS RPLC',
            'RPUI' => 'RPUS RPUI',
            'RPUR' => 'RPUS RPUR',
        ];

        return $routes[$destination] ?? 'DCT ' . $destination;
    }

    private function calculateEET(string $destination): string
    {
        $eettimes = [
            'RPUQ' => '0100',
            'RPLB' => '0200',
            'RPLC' => '0200',
            'RPUI' => '0200',
            'RPUR' => '0200',
        ];

        return $eettimes[$destination] ?? '0130';
    }

    private function calculateEndurance(string $aircraftType): string
    {
        $endurances = [
            'C172' => '0500',
            'SIRA' => '0500',
            'C152' => '0430',
        ];

        return $endurances[$aircraftType] ?? '0400';
    }
}
