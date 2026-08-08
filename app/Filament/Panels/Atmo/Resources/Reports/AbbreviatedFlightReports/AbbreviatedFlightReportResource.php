<?php
namespace App\Filament\Panels\Atmo\Resources\Reports\AbbreviatedFlightReports;
use App\Filament\Shared\Resources\Reports\AbbreviatedFlightReportResource as SharedAbbreviatedFlightReportResource;
use App\Filament\Panels\Atmo\Resources\Reports\AbbreviatedFlightReports\Pages\ListAbbreviatedFlightReports;
class AbbreviatedFlightReportResource extends SharedAbbreviatedFlightReportResource
{
    public static function getPages(): array
    {
        return [
            'index' => ListAbbreviatedFlightReports::route('/'),
        ];
    }
}