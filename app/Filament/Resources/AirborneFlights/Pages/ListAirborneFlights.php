<?php

namespace App\Filament\Resources\AirborneFlights\Pages;

use App\Filament\Resources\AirborneFlights\AirborneFlightResource;
use Filament\Resources\Pages\ListRecords;

class ListAirborneFlights extends ListRecords
{
    protected static string $resource = AirborneFlightResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
