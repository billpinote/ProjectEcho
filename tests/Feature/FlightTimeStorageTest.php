<?php

namespace Tests\Feature;

use App\Models\Flight;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlightTimeStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_flight_model_stores_minute_precision_time_fields_as_hh_mm(): void
    {
        $flight = Flight::create([
            'proposed_time' => '1430',
            'total_eet' => '02:30:45',
            'endurance' => '04:00',
            'time_start_up' => '0815',
            'time_shutdown' => '08:45:59',
            'time_block_off' => '08:20',
            'time_block_on' => '10:10:01',
            'time_airborne' => '0830',
            'time_touchdown' => '1005',
            'received_time' => '08:12:33',
        ]);

        $flight->refresh();

        $this->assertSame('14:30', $flight->proposed_time);
        $this->assertSame('02:30', $flight->total_eet);
        $this->assertSame('04:00', $flight->endurance);
        $this->assertSame('08:15', $flight->time_start_up);
        $this->assertSame('08:45', $flight->time_shutdown);
        $this->assertSame('08:20', $flight->time_block_off);
        $this->assertSame('10:10', $flight->time_block_on);
        $this->assertSame('08:30', $flight->time_airborne);
        $this->assertSame('10:05', $flight->time_touchdown);
        $this->assertSame('08:12', $flight->received_time);
    }

    public function test_flight_timestamps_keep_second_precision_for_sequencing(): void
    {
        $flight = Flight::create([
            'proposed_time' => '1430',
        ]);

        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $flight->getRawOriginal('created_at'));
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $flight->getRawOriginal('updated_at'));
    }
}
