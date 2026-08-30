<?php

namespace App\Filament\Shared\Resources\ActiveFlights\Pages;

use App\Filament\Shared\Resources\ActiveFlights\ActiveFlightResource;
use App\Models\Flight;
use App\Models\FlightPlanEvent;
use Filament\Resources\Pages\ListRecords;

class ListActiveFlights extends ListRecords
{
    protected static string $resource = ActiveFlightResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function confirmAirborneNow(string|int $recordId): void
    {
        $record = Flight::query()->findOrFail($recordId);
        abort_unless(auth()->user()?->can('updateAirborneTime', $record) ?? false, 403);

        $oldValue = $record->time_airborne;
        $record->forceFill([
            'time_airborne' => now('UTC')->format('H:i'),
        ])->save();
        FlightPlanEvent::record($record, FlightPlanEvent::TYPE_AIRBORNE, auth()->user(), ['time_airborne' => $oldValue], ['time_airborne' => $record->time_airborne]);
    }
}
