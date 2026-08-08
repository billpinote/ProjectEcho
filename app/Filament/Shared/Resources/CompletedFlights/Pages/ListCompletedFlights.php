<?php

namespace App\Filament\Shared\Resources\CompletedFlights\Pages;

use App\Filament\Shared\Resources\CompletedFlights\CompletedFlightResource;
use Filament\Resources\Pages\ListRecords;

class ListCompletedFlights extends ListRecords
{
    protected static string $resource = CompletedFlightResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
