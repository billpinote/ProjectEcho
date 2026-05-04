<?php

namespace App\Filament\Resources\Reports;

use App\Filament\Resources\Flights\FlightResource;
use App\Filament\Resources\Reports\Pages\ListAbbreviatedFlightReports;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class AbbreviatedFlightReportResource extends FlightResource
{
    protected static ?string $navigationParentItem = 'Reports';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Abbreviated';

    protected static ?string $modelLabel = 'abbreviated flight report';

    protected static ?string $pluralModelLabel = 'abbreviated flight reports';

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        $query = static::getFlightPlanBaseQuery()->where('received_facility', 'RPUS');

        if (! static::hasStatusColumn()) {
            return $query->whereNotNull('accepted_by_user_id');
        }

        return $query->accepted();
    }

    public static function getNavigationBadge(): ?string
    {
        return null;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAbbreviatedFlightReports::route('/'),
        ];
    }
}
