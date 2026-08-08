<?php
namespace App\Filament\Panels\Pilot\Resources\Flights;
use App\Filament\Shared\Resources\Flights\FlightResource as SharedFlightResource;
use App\Filament\Panels\Pilot\Resources\Flights\Pages\ListFlights;
use App\Filament\Panels\Pilot\Resources\Flights\Pages\CreateFlight;
use App\Filament\Panels\Pilot\Resources\Flights\Pages\EditFlight;
class FlightResource extends SharedFlightResource
{
    public static function getPages(): array
    {
        return [
            'index' => ListFlights::route('/'),
            'create' => CreateFlight::route('/create'),
            'edit' => EditFlight::route('/{record}/edit'),
        ];
    }
}