<?php

namespace Database\Seeders;

use App\Models\Operator;
use Illuminate\Database\Seeder;

class OperatorSeeder extends Seeder
{
    public function run(): void
    {
        $operators = [
            [
                'name' => 'Philippine Airlines',
                'icao_code' => 'PAL',
                'certificate_number' => 'CERT-0001',
                'address' => 'PASA, Pasay City, Philippines',
                'contact_number' => '+63 2 8555 0000',
                'email' => 'info@pal.com',
                'remarks' => 'Flag carrier of the Philippines.',
            ],
            [
                'name' => 'Cebu Pacific',
                'icao_code' => 'CEB',
                'certificate_number' => 'CERT-0002',
                'address' => 'PASA, Pasay City, Philippines',
                'contact_number' => '+63 2 8702 0888',
                'email' => 'support@cebupacificair.com',
                'remarks' => 'Low-cost airline.',
            ],
            [
                'name' => 'Alpha Aviation',
                'icao_code' => 'AAV',
                'certificate_number' => 'CERT-0003',
                'address' => 'Clark Freeport Zone, Philippines',
                'contact_number' => '+63 45 599 0000',
                'email' => 'contact@alphaaviation.ph',
                'remarks' => 'Flight training and charter operator.',
            ],
        ];

        foreach ($operators as $operator) {
            Operator::query()->updateOrCreate(
                ['icao_code' => $operator['icao_code']],
                $operator,
            );
        }
    }
}
