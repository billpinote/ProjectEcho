<?php

namespace App\Filament\Resources\RejectedFlights\Pages;

use App\Filament\Resources\RejectedFlights\RejectedFlightResource;
use Filament\Resources\Pages\ListRecords;

class ListRejectedFlights extends ListRecords
{
    protected static string $resource = RejectedFlightResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
