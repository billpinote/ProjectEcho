<?php

namespace App\Filament\Shared\Resources\Reports;

use App\Domain\FlightPlans\Services\FlightReportQueryService;
use App\Filament\Shared\Resources\Flights\FlightResource;
use App\Filament\Shared\Resources\Reports\Pages\ListPostOpsLogs;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class PostOpsLogResource extends FlightResource
{
    protected static ?string $navigationParentItem = 'Coordinator';

    protected static string|\UnitEnum|null $navigationGroup = null;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Post-Ops Log';

    protected static ?string $modelLabel = 'post ops log';

    protected static ?string $pluralModelLabel = 'post ops logs';

    protected static ?int $navigationSort = 4;

    public static function getEloquentQuery(): Builder
    {
        return app(FlightReportQueryService::class)->postOperations(Auth::user());
    }

    public static function getNavigationBadge(): ?string
    {
        return null;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPostOpsLogs::route('/'),
        ];
    }
}
