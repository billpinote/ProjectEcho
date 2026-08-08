<?php

namespace App\Filament\Shared\Resources\Reports\Pages;

use App\Filament\Shared\Resources\Reports\AbbreviatedFlightReportResource;
use Filament\Resources\Pages\ListRecords;

class ListAbbreviatedFlightReports extends ListRecords
{
    protected static string $resource = AbbreviatedFlightReportResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
