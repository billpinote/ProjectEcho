<?php

namespace App\Filament\Resources\AirborneFlights\Pages;

use App\Filament\Resources\AirborneFlights\AirborneFlightResource;
use App\Models\Flight;
use Filament\Resources\Pages\ListRecords;

class ListAirborneFlights extends ListRecords
{
    protected static string $resource = AirborneFlightResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function confirmTouchdownNow(string|int $recordId): void
    {
        abort_unless(auth()->user()?->canUpdateFlightPlans() ?? false, 403);

        $record = Flight::query()->findOrFail($recordId);

        $record->forceFill([
            'time_touchdown' => now('UTC')->format('H:i'),
        ])->save();
    }
}
