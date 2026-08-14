<?php

namespace App\Filament\Panels\Dispatch\Resources\Flights\Pages;

use App\Filament\Panels\Dispatch\Resources\Flights\FlightResource;
use App\Filament\Shared\Resources\Flights\Pages\CreateFlight as SharedCreateFlight;
use Filament\Pages\Dashboard;

class CreateFlight extends SharedCreateFlight
{
    protected static string $resource = FlightResource::class;

    protected function getRedirectUrl(): string
    {
        return Dashboard::getUrl(panel: 'dispatch');
    }
}
