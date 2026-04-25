<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MINUTE_PRECISION_TIME_FIELDS = [
        'proposed_time',
        'total_eet',
        'endurance',
        'time_start_up',
        'time_shutdown',
        'time_block_off',
        'time_block_on',
        'time_airborne',
        'time_touchdown',
        'received_time',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('flights')
            ->select(array_merge(['id'], self::MINUTE_PRECISION_TIME_FIELDS))
            ->orderBy('id')
            ->chunkById(100, function ($flights): void {
                foreach ($flights as $flight) {
                    $updates = [];

                    foreach (self::MINUTE_PRECISION_TIME_FIELDS as $field) {
                        $normalized = $this->normalizeMinutePrecisionTime($flight->{$field});

                        if ($normalized !== $flight->{$field}) {
                            $updates[$field] = $normalized;
                        }
                    }

                    if ($updates === []) {
                        continue;
                    }

                    DB::table('flights')
                        ->where('id', $flight->id)
                        ->update($updates);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}

    private function normalizeMinutePrecisionTime(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $time = trim((string) $value);

        if ($time === '') {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}(?::\d{2})?$/', $time) !== 1) {
            return $time;
        }

        [$hours, $minutes] = array_map('intval', explode(':', substr($time, 0, 5)));

        if ($hours > 23 || $minutes > 59) {
            return $time;
        }

        return sprintf('%02d:%02d', $hours, $minutes);
    }
};
