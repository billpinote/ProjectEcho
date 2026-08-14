<?php

namespace App\Filament\Panels\Dispatch\Resources\Flights;

use App\Filament\Panels\Dispatch\Resources\Flights\Pages\CreateFlight;
use App\Filament\Shared\Resources\Flights\FlightResource as SharedFlightResource;

class FlightResource extends SharedFlightResource
{
    public static function getPages(): array
    {
        return [
            'create' => CreateFlight::route('/create'),
        ];
    }
}
