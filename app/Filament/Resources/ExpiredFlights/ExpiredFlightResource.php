<?php

namespace App\Filament\Resources\ExpiredFlights;

use App\Filament\Resources\ExpiredFlights\Pages\EditExpiredFlight;
use App\Filament\Resources\ExpiredFlights\Pages\ListExpiredFlights;
use App\Filament\Resources\Flights\FlightResource;
use Illuminate\Database\Eloquent\Builder;

class ExpiredFlightResource extends FlightResource
{
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
        if (! static::hasStatusColumn()) {
            return null;
        }

        $count = static::getModel()::query()->pendingExpired()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Pending flight plans whose departure time has already passed';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExpiredFlights::route('/'),
            'edit' => EditExpiredFlight::route('/{record}/edit'),
        ];
    }
}
