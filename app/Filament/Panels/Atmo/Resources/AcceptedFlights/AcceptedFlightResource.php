<?php
namespace App\Filament\Panels\Atmo\Resources\AcceptedFlights;
use App\Filament\Shared\Resources\AcceptedFlights\AcceptedFlightResource as SharedAcceptedFlightResource;
use App\Filament\Panels\Atmo\Resources\AcceptedFlights\Pages\ListAcceptedFlights;
use App\Filament\Panels\Atmo\Resources\AcceptedFlights\Pages\EditAcceptedFlight;
class AcceptedFlightResource extends SharedAcceptedFlightResource
{
    public static function getPages(): array
    {
        return [
            'index' => ListAcceptedFlights::route('/'),
            'edit' => EditAcceptedFlight::route('/{record}/edit'),
        ];
    }
}