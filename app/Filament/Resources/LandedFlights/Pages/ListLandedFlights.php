<?php

namespace App\Filament\Resources\LandedFlights\Pages;

use App\Filament\Resources\LandedFlights\LandedFlightResource;
use App\Models\Flight;
use Filament\Resources\Pages\ListRecords;

class ListLandedFlights extends ListRecords
{
    protected static string $resource = LandedFlightResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function confirmShutdownNow(string|int $recordId): void
    {
        $record = Flight::query()->findOrFail($recordId);

        $record->forceFill([
            'time_shutdown' => now('UTC')->format('H:i'),
        ])->save();
    }
}
