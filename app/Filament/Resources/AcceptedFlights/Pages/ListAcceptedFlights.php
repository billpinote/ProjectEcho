<?php

namespace App\Filament\Resources\AcceptedFlights\Pages;

use App\Filament\Resources\AcceptedFlights\AcceptedFlightResource;
use Filament\Resources\Pages\ListRecords;

class ListAcceptedFlights extends ListRecords
{
    protected static string $resource = AcceptedFlightResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
