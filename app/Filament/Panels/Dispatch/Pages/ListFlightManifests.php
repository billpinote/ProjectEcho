<?php

namespace App\Filament\Panels\Dispatch\Pages;

use App\Filament\Panels\Dispatch\Resources\Flights\FlightResource;
use App\Filament\Shared\Pages\ListFlightManifests as SharedListFlightManifests;

class ListFlightManifests extends SharedListFlightManifests
{
    protected function createFlightUrl(): string
    {
        return FlightResource::getUrl('create', panel: 'dispatch');
    }
}
