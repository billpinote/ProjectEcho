<?php

namespace App\Filament\Resources\Reports;

use App\Filament\Resources\Flights\FlightResource;
use App\Filament\Resources\Reports\Pages\ListPostOpsLogs;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class PostOpsLogResource extends FlightResource
{
    protected static ?string $navigationParentItem = 'Reports';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Post Ops Log';

    protected static ?string $modelLabel = 'post ops log';

    protected static ?string $pluralModelLabel = 'post ops logs';

    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        $query = static::getFlightPlanBaseQuery()
            ->where('received_facility', 'RPUS')
            ->whereNotNull('time_airborne');

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
            'index' => ListPostOpsLogs::route('/'),
        ];
    }
}
