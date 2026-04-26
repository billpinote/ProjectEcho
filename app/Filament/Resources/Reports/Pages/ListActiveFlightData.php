<?php

namespace App\Filament\Resources\Reports\Pages;

use App\Filament\Resources\Reports\ActiveFlightDataResource;
use Filament\Resources\Pages\ListRecords;

class ListActiveFlightData extends ListRecords
{
    protected static string $resource = ActiveFlightDataResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
