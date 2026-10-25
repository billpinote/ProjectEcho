<?php

namespace App\Filament\Shared\Resources\Reports;

use App\Domain\FlightPlans\Services\FlightReportQueryService;
use App\Filament\Shared\Resources\Flights\FlightResource;
use App\Filament\Shared\Resources\Reports\Pages\ListAbbreviatedFlightReports;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class AbbreviatedFlightReportResource extends FlightResource
{
    protected static ?string $navigationParentItem = 'Coordinator';

    protected static string|\UnitEnum|null $navigationGroup = null;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Abbreviated Flight Report';

    protected static ?string $modelLabel = 'abbreviated flight report';

    protected static ?string $pluralModelLabel = 'abbreviated flight reports';

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return app(FlightReportQueryService::class)->abbreviated(Auth::user());
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
