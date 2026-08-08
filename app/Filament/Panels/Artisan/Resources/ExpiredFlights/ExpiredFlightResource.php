<?php
namespace App\Filament\Panels\Artisan\Resources\ExpiredFlights;
use App\Filament\Shared\Resources\ExpiredFlights\ExpiredFlightResource as SharedExpiredFlightResource;
use App\Filament\Panels\Artisan\Resources\ExpiredFlights\Pages\ListExpiredFlights;
use App\Filament\Panels\Artisan\Resources\ExpiredFlights\Pages\EditExpiredFlight;
class ExpiredFlightResource extends SharedExpiredFlightResource
{
    public static function getPages(): array
    {
        return [
            'index' => ListExpiredFlights::route('/'),
            'edit' => EditExpiredFlight::route('/{record}/edit'),
        ];
    }
}