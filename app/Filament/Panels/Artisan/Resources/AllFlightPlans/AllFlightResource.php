<?php
namespace App\Filament\Panels\Artisan\Resources\AllFlightPlans;
use App\Filament\Shared\Resources\AllFlightPlans\AllFlightResource as SharedAllFlightResource;
use App\Filament\Panels\Artisan\Resources\AllFlightPlans\Pages\ListAllFlights;
use App\Filament\Panels\Artisan\Resources\AllFlightPlans\Pages\EditAllFlight;
class AllFlightResource extends SharedAllFlightResource
{
    public static function getPages(): array
    {
        return [
            'index' => ListAllFlights::route('/'),
            'edit' => EditAllFlight::route('/{record}/edit'),
        ];
    }
}