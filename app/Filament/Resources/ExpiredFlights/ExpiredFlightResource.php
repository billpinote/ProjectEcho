<?php

namespace App\Filament\Resources\ExpiredFlights;

use App\Filament\Resources\ExpiredFlights\Pages\EditExpiredFlight;
use App\Filament\Resources\ExpiredFlights\Pages\ListExpiredFlights;
use App\Filament\Resources\Flights\FlightResource;
use Illuminate\Database\Eloquent\Builder;
use Filament\Support\Icons\Heroicon;

class ExpiredFlightResource extends FlightResource
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?string $navigationLabel = 'Expired';

    protected static ?string $modelLabel = 'expired flight plan';

    protected static ?string $pluralModelLabel = 'expired flight plans';

    protected static ?int $navigationSort = 4;

    public static function getEloquentQuery(): Builder
    {
        if (! static::hasStatusColumn()) {
            return static::getFlightPlanBaseQuery()->whereRaw('1 = 0');
        }

        return static::getFlightPlanBaseQuery()->pendingExpired();
    }

    public static function getNavigationBadge(): ?string
    {
        return null;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExpiredFlights::route('/'),
            'edit' => EditExpiredFlight::route('/{record}/edit'),
        ];
    }
}
