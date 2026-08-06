<?php

namespace App\Filament\Resources\MyArchivedFlights\Pages;

use App\Filament\Resources\MyArchivedFlights\MyArchivedFlightResource;
use Filament\Resources\Pages\ListRecords;

class ListMyArchivedFlights extends ListRecords
{
    protected static string $resource = MyArchivedFlightResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
