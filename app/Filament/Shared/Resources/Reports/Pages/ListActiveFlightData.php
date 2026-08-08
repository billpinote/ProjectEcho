<?php

namespace App\Filament\Shared\Resources\Reports\Pages;

use App\Filament\Shared\Resources\Reports\ActiveFlightDataResource;
use Filament\Resources\Pages\ListRecords;

class ListActiveFlightData extends ListRecords
{
    protected static string $resource = ActiveFlightDataResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
