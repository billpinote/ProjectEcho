<?php

namespace App\Filament\Panels\Pilot\Pages;

use App\Filament\Panels\Pilot\Resources\Flights\Pages\CreateFlight;
use App\Filament\Shared\Pages\ListFlightManifests as SharedListFlightManifests;

class ListFlightManifests extends SharedListFlightManifests
{
    protected function createFlightUrl(): string
    {
        return CreateFlight::getUrl(panel: 'pilot');
    }
}
