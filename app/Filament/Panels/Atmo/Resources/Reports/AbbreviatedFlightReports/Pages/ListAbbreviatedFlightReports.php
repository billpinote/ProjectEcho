<?php
namespace App\Filament\Panels\Atmo\Resources\Reports\AbbreviatedFlightReports\Pages;
use App\Filament\Panels\Atmo\Resources\Reports\AbbreviatedFlightReports\AbbreviatedFlightReportResource;
use App\Filament\Shared\Resources\Reports\Pages\ListAbbreviatedFlightReports as SharedListAbbreviatedFlightReports;
class ListAbbreviatedFlightReports extends SharedListAbbreviatedFlightReports
{
    protected static string $resource = AbbreviatedFlightReportResource::class;
}