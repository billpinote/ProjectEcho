<?php

namespace App\Filament\Resources\ActiveFlights\Pages;

use App\Filament\Resources\ActiveFlights\ActiveFlightResource;
use Filament\Resources\Pages\ListRecords;

class ListActiveFlights extends ListRecords
{
    protected static string $resource = ActiveFlightResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
