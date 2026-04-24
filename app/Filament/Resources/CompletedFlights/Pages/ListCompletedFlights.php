<?php

namespace App\Filament\Resources\CompletedFlights\Pages;

use App\Filament\Resources\CompletedFlights\CompletedFlightResource;
use Filament\Resources\Pages\ListRecords;

class ListCompletedFlights extends ListRecords
{
    protected static string $resource = CompletedFlightResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
