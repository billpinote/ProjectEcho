<?php

namespace App\Filament\Shared\Resources\LandedFlights\Pages;

use App\Filament\Shared\Resources\LandedFlights\LandedFlightResource;
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
        abort_unless(auth()->user()?->can('updateShutdownTime', $record) ?? false, 403);

        $record->forceFill([
            'time_shutdown' => now('UTC')->format('H:i'),
        ])->save();
    }
}
