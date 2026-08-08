<?php

namespace App\Filament\Panels\Pilot\Resources\MyCompletedFlights\Pages;

use App\Filament\Panels\Pilot\Resources\MyCompletedFlights\MyCompletedFlightResource;
use Filament\Resources\Pages\ListRecords;

class ListMyCompletedFlights extends ListRecords
{
    protected static string $resource = MyCompletedFlightResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
