<?php

namespace App\Filament\Panels\Pilot\Resources\AwaitingAuthorizationFlights\Pages;

use App\Filament\Panels\Pilot\Resources\AwaitingAuthorizationFlights\AwaitingAuthorizationFlightResource;
use Filament\Resources\Pages\ListRecords;

class ListAwaitingAuthorizationFlights extends ListRecords
{
    protected static string $resource = AwaitingAuthorizationFlightResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
