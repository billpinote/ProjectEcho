<?php

namespace App\Filament\Shared\Resources\AcceptedFlights\Pages;

use App\Filament\Shared\Resources\AcceptedFlights\AcceptedFlightResource;
use App\Models\Flight;
use App\Models\FlightPlanEvent;
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

        $oldValue = $record->time_start_up;
        $record->forceFill([
            'time_start_up' => now('UTC')->format('H:i'),
        ])->save();
        FlightPlanEvent::record($record, FlightPlanEvent::TYPE_STARTUP_RECORDED, auth()->user(), ['time_start_up' => $oldValue], ['time_start_up' => $record->time_start_up]);
    }

    public function confirmBlockOffNow(string|int $recordId): void
    {
        $record = Flight::query()->findOrFail($recordId);
        abort_unless(auth()->user()?->can('updateBlockOffTime', $record) ?? false, 403);

        $oldValue = $record->time_block_off;
        $record->forceFill([
            'time_block_off' => now('UTC')->format('H:i'),
        ])->save();
        FlightPlanEvent::record($record, FlightPlanEvent::TYPE_BLOCK_OFF_RECORDED, auth()->user(), ['time_block_off' => $oldValue], ['time_block_off' => $record->time_block_off]);
    }
}
