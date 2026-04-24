<?php

namespace App\Filament\Resources\LandedFlights\Pages;

use App\Filament\Resources\LandedFlights\LandedFlightResource;
use Filament\Resources\Pages\ListRecords;

class ListLandedFlights extends ListRecords
{
    protected static string $resource = LandedFlightResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
