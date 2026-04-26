<?php

namespace Database\Seeders;

use App\Enums\FlightPlanStatus;
use App\Models\Flight;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class PendingFlightPlansSeeder extends Seeder
{
    public function run(): void
    {
        $today = Carbon::now('Asia/Manila')->startOfDay();

        $flights = [
            [
                'date_offset' => 0,
                'aircraft_identification' => 'RPC3211',
                'type_of_aircraft' => 'C172',
                'departure_aerodrome' => 'RPLL',
                'destination_aerodrome' => 'RPVP',
                'proposed_time' => '0730',
                'total_eet' => '0145',
                'endurance' => '0430',
                'route' => 'DCT NINON DCT VCV',
                'pilot_in_command' => 'JUAN DELA CRUZ',
                'flight_rules' => 'V',
                'type_of_flight' => 'G',
                'persons_on_board' => 4,
            ],
            [
                'date_offset' => 0,
                'aircraft_identification' => 'RPX8802',
                'type_of_aircraft' => 'PA34',
                'departure_aerodrome' => 'RPLC',
                'destination_aerodrome' => 'RPLB',
                'proposed_time' => '0915',
                'total_eet' => '0105',
                'endurance' => '0400',
                'route' => 'DCT OLMEN DCT SUBIC',
                'pilot_in_command' => 'MARIA SANTOS',
                'flight_rules' => 'V',
                'type_of_flight' => 'G',
                'persons_on_board' => 5,
            ],
            [
                'date_offset' => 0,
                'aircraft_identification' => 'RPQ4410',
                'type_of_aircraft' => 'BE20',
                'departure_aerodrome' => 'RPMD',
                'destination_aerodrome' => 'RPVM',
                'proposed_time' => '1120',
                'total_eet' => '0125',
                'endurance' => '0500',
                'route' => 'DCT AGSAM DCT SURIGAO',
                'pilot_in_command' => 'ROBERTO LIM',
                'flight_rules' => 'I',
                'type_of_flight' => 'N',
                'persons_on_board' => 6,
            ],
            [
                'date_offset' => 1,
                'aircraft_identification' => 'RPT5507',
                'type_of_aircraft' => 'A320',
                'departure_aerodrome' => 'RPVM',
                'destination_aerodrome' => 'RPLL',
                'proposed_time' => '1345',
                'total_eet' => '0135',
                'endurance' => '0520',
                'route' => 'DCT KABAR A464 VAMPI',
                'pilot_in_command' => 'CARLOS NAVARRO',
                'flight_rules' => 'I',
                'type_of_flight' => 'S',
                'persons_on_board' => 142,
            ],
            [
                'date_offset' => 1,
                'aircraft_identification' => 'RPU7719',
                'type_of_aircraft' => 'B350',
                'departure_aerodrome' => 'RPVK',
                'destination_aerodrome' => 'RPMZ',
                'proposed_time' => '1610',
                'total_eet' => '0100',
                'endurance' => '0415',
                'route' => 'DCT CEB DCT ZAMBO',
                'pilot_in_command' => 'ELENA REYES',
                'flight_rules' => 'I',
                'type_of_flight' => 'N',
                'persons_on_board' => 8,
            ],
        ];

        foreach ($flights as $flight) {
            $dateOfFlight = $today->copy()->addDays($flight['date_offset'])->toDateString();

            Flight::query()->updateOrCreate(
                [
                    'aircraft_identification' => $flight['aircraft_identification'],
                    'date_of_flight' => $dateOfFlight,
                ],
                [
                    'addressees' => 'RPLLZQZX',
                    'originator' => 'PROJECTECHO',
                    'date_of_filing' => $today->toDateString(),
                    'date_of_flight' => $dateOfFlight,
                    'aircraft_identification' => $flight['aircraft_identification'],
                    'flight_rules' => $flight['flight_rules'],
                    'type_of_flight' => $flight['type_of_flight'],
                    'number' => '1',
                    'type_of_aircraft' => $flight['type_of_aircraft'],
                    'wake_turbulence_cat' => 'L',
                    'equipment_10a' => 'SDFGIRY',
                    'equipment_10b' => 'S',
                    'departure_aerodrome' => $flight['departure_aerodrome'],
                    'proposed_time' => $flight['proposed_time'],
                    'cruising_speed' => 'N0110',
                    'level' => 'A045',
                    'route' => $flight['route'],
                    'destination_aerodrome' => $flight['destination_aerodrome'],
                    'total_eet' => $flight['total_eet'],
                    'altn_aerodrome_1' => 'RPVA',
                    'altn_aerodrome_2' => 'RPSG',
                    'other_information' => 'DOF/'.str_replace('-', '', $dateOfFlight).' RMK/SEEDED PENDING FLIGHT',
                    'other_info_dof' => $dateOfFlight,
                    'other_info_rmk' => 'SEEDED PENDING FLIGHT',
                    'endurance' => $flight['endurance'],
                    'persons_on_board' => $flight['persons_on_board'],
                    'aircraft_colour_and_markings' => 'WHITE BLUE',
                    'remarks' => 'AUTO SEEDED FOR TABLE TESTING',
                    'pilot_in_command' => $flight['pilot_in_command'],
                    'filed_by_name' => $flight['pilot_in_command'],
                    'filed_by_signature' => $flight['pilot_in_command'],
                    'pilot_license_no' => 'LIC-'.substr($flight['aircraft_identification'], -4),
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
}
