<?php
namespace App\Filament\Panels\Artisan\Resources\AirborneFlights;
use App\Filament\Shared\Resources\AirborneFlights\AirborneFlightResource as SharedAirborneFlightResource;
use App\Filament\Panels\Artisan\Resources\AirborneFlights\Pages\ListAirborneFlights;
use App\Filament\Panels\Artisan\Resources\AirborneFlights\Pages\EditAirborneFlight;
class AirborneFlightResource extends SharedAirborneFlightResource
{
    public static function getPages(): array
    {
        return [
            'index' => ListAirborneFlights::route('/'),
            'edit' => EditAirborneFlight::route('/{record}/edit'),
        ];
    }
}