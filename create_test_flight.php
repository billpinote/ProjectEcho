<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$flight = App\Models\Flight::create([
    'aircraft_identification' => 'RP-C1234',
    'flight_rules' => 'I',
    'type_of_flight' => 'S',
    'number' => 'ABC123',
    'type_of_aircraft' => 'A320',
    'wake_turbulence_cat' => 'M',
    'equipment_10a' => 'S',
    'equipment_10b' => 'DE',
    'departure_aerodrome' => 'RPLL',
    'proposed_time' => '0800',
    'cruising_speed' => 'N0450',
    'level' => 'F350',
    'route' => 'DVO W1 BKK',
    'destination_aerodrome' => 'VTBS',
    'total_eet' => '0300',
    'altn_aerodrome_1' => 'VTPP',
    'other_info_dof' => '20241201',
    'other_info_rmk' => 'TCAS EQUIPPED',
    'other_info_typ' => 'A320',
    'other_info_reg' => 'RP-C1234',
    'other_info_pbn' => 'B1',
    'other_info_opr' => 'ABC AIRLINES',
    'endurance' => '0500',
    'persons_on_board' => 150,
    'pilot_in_command' => 'JOHN DOE',
]);

echo 'Created flight with ID: ' . $flight->id . PHP_EOL;