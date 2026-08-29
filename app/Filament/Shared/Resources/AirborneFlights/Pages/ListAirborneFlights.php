<?php

namespace App\Filament\Shared\Resources\AirborneFlights\Pages;

use App\Filament\Shared\Resources\AirborneFlights\AirborneFlightResource;
use App\Models\Flight;
use App\Models\FlightPlanEvent;
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
        $record = Flight::query()->findOrFail($recordId);
        abort_unless(auth()->user()?->can('updateTouchdownTime', $record) ?? false, 403);

        $oldValue = $record->time_touchdown;
        $record->forceFill([
            'time_touchdown' => now('UTC')->format('H:i'),
        ])->save();
        FlightPlanEvent::record($record, FlightPlanEvent::TYPE_TOUCHDOWN, auth()->user(), ['time_touchdown' => $oldValue], ['time_touchdown' => $record->time_touchdown]);
    }
}
