<?php
namespace App\Filament\Panels\Artisan\Resources\LandedFlights;
use App\Filament\Shared\Resources\LandedFlights\LandedFlightResource as SharedLandedFlightResource;
use App\Filament\Panels\Artisan\Resources\LandedFlights\Pages\ListLandedFlights;
use App\Filament\Panels\Artisan\Resources\LandedFlights\Pages\EditLandedFlight;
class LandedFlightResource extends SharedLandedFlightResource
{
    public static function getPages(): array
    {
        return [
            'index' => ListLandedFlights::route('/'),
            'edit' => EditLandedFlight::route('/{record}/edit'),
        ];
    }
}