<?php

namespace App\Filament\Shared\Resources\ExpiredFlights\Pages;

use App\Filament\Shared\Resources\ExpiredFlights\ExpiredFlightResource;
use Filament\Resources\Pages\ListRecords;

class ListExpiredFlights extends ListRecords
{
    protected static string $resource = ExpiredFlightResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
