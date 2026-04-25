<?php

namespace App\Filament\Resources\AcceptedFlights\Pages;

use App\Filament\Resources\AcceptedFlights\AcceptedFlightResource;
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

        $record->forceFill([
            'time_start_up' => now('UTC')->format('H:i'),
        ])->save();
    }
}
