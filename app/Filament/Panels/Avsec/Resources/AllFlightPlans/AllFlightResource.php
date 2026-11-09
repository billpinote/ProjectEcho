<?php

namespace App\Filament\Panels\Avsec\Resources\AllFlightPlans;

use App\Filament\Panels\Avsec\Resources\AllFlightPlans\Pages\EditAllFlight;
use App\Filament\Panels\Avsec\Resources\AllFlightPlans\Pages\ListAllFlights;
use App\Filament\Shared\Resources\AllFlightPlans\AllFlightResource as SharedAllFlightResource;

class AllFlightResource extends SharedAllFlightResource
{
    protected static bool $shouldRegisterNavigation = false;

    public static function getPages(): array
    {
        return [
            'index' => ListAllFlights::route('/'),
            'edit' => EditAllFlight::route('/{record}/edit'),
        ];
    }
}
