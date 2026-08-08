<?php
namespace App\Filament\Panels\Atmo\Resources\RejectedFlights;
use App\Filament\Shared\Resources\RejectedFlights\RejectedFlightResource as SharedRejectedFlightResource;
use App\Filament\Panels\Atmo\Resources\RejectedFlights\Pages\ListRejectedFlights;
use App\Filament\Panels\Atmo\Resources\RejectedFlights\Pages\EditRejectedFlight;
class RejectedFlightResource extends SharedRejectedFlightResource
{
    public static function getPages(): array
    {
        return [
            'index' => ListRejectedFlights::route('/'),
            'edit' => EditRejectedFlight::route('/{record}/edit'),
        ];
    }
}