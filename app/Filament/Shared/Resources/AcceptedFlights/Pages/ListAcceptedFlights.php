<?php

namespace App\Filament\Shared\Resources\AcceptedFlights\Pages;

use App\Filament\Shared\Resources\AcceptedFlights\AcceptedFlightResource;
use App\Models\Flight;
use Filament\Resources\Pages\ListRecords;

class ListAcceptedFlights extends ListRecords
{
    protected static string $resource = AcceptedFlightResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function confirmStartUpNow(string|int $recordId): void
    {
        $record = Flight::query()->findOrFail($recordId);
        abort_unless(auth()->user()?->can('updateStartUpTime', $record) ?? false, 403);

        $record->forceFill([
            'time_start_up' => now('UTC')->format('H:i'),
        ])->save();
    }

    public function confirmBlockOffNow(string|int $recordId): void
    {
        $record = Flight::query()->findOrFail($recordId);
        abort_unless(auth()->user()?->can('updateBlockOffTime', $record) ?? false, 403);

        $record->forceFill([
            'time_block_off' => now('UTC')->format('H:i'),
        ])->save();
    }
}
