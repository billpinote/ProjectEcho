<?php
namespace App\Filament\Panels\Atmo\Resources\CompletedFlights;
use App\Filament\Shared\Resources\CompletedFlights\CompletedFlightResource as SharedCompletedFlightResource;
use App\Filament\Panels\Atmo\Resources\CompletedFlights\Pages\ListCompletedFlights;
use App\Filament\Panels\Atmo\Resources\CompletedFlights\Pages\EditCompletedFlight;
class CompletedFlightResource extends SharedCompletedFlightResource
{
    public static function getPages(): array
    {
        return [
            'index' => ListCompletedFlights::route('/'),
            'edit' => EditCompletedFlight::route('/{record}/edit'),
        ];
    }
}