<?php

namespace App\Filament\Resources\Reports;

use App\Filament\Resources\Flights\FlightResource;
use App\Filament\Resources\Reports\Pages\ListActiveFlightData;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ActiveFlightDataResource extends FlightResource
{
    protected static ?string $navigationParentItem = 'Reports';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static ?string $navigationLabel = 'Active Flight Data';

    protected static ?string $modelLabel = 'active flight data record';

    protected static ?string $pluralModelLabel = 'active flight data';

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        if (! static::hasStatusColumn()) {
            return static::getFlightPlanBaseQuery()->whereNotNull('accepted_by_user_id');
        }

        return static::getFlightPlanBaseQuery()->accepted();
    }

    public static function getNavigationBadge(): ?string
    {
        return null;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActiveFlightData::route('/'),
        ];
    }
}
