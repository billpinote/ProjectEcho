<?php

namespace App\Filament\Resources\MyCompletedFlights\Pages;

use App\Filament\Resources\MyCompletedFlights\MyCompletedFlightResource;
use Filament\Resources\Pages\ListRecords;

class ListMyCompletedFlights extends ListRecords
{
    protected static string $resource = MyCompletedFlightResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
