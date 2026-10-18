<?php

namespace App\Console\Commands;

use App\Domain\FlightPlans\Services\ManifestPdfService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class GenerateManifestPdfSamples extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'manifest:pdf-samples';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate five synthetic manifest PDFs for visual calibration without database writes';

    /**
     * Execute the console command.
     */
    public function handle(ManifestPdfService $pdf): int
    {
        foreach (['pic-only' => 1, 'three-passengers' => 4, 'full-25' => 25, 'continuation-30' => 30, 'long-names' => 4] as $name => $count) {
            $persons = [];
            foreach (range(1, $count) as $index) {
                $persons[] = [
                    'full_name' => $index === 1 ? 'Juan Dela Cruz' : ($name === 'long-names' ? 'María Concepción Alexandra Isabel de los Santos Fernández '.$index : 'Sample Passenger '.$index),
                    'nationality' => ['Filipino', 'Japanese', 'Spanish', 'American'][($index - 1) % 4],
                    'classification' => $index === 1 ? 'pic' : 'passenger',
                ];
            }
            $bytes = $pdf->render([
                'aircraft_identification' => 'RP-C1234', 'type_of_aircraft' => 'C172', 'date_of_flight' => '2026-10-18',
                'home_base_hangar' => 'RPUS Hangar 1', 'aircraft_owner_name' => 'Sample Aircraft Owner',
                'aircraft_owner_contact_number' => '+63 900 000 0000', 'coa_expiry_date' => '2027-12-31',
                'cargo_type' => 'Baggage', 'cargo_quantity' => '2 bags', 'total_cargo_weight' => '20 kg',
                'pilot_in_command' => 'Juan Dela Cruz', 'pilot_license_no' => 'SAMPLE-12345',
                'pilot_ratings' => 'ASEL / IR', 'license_expiry_date' => '2027-12-31', 'persons' => $persons,
            ]);
            $path = 'manifest-samples/'.$name.'.pdf';
            Storage::disk('local')->put($path, $bytes);
            $this->line(Storage::disk('local')->path($path));
        }

        return self::SUCCESS;
    }
}
