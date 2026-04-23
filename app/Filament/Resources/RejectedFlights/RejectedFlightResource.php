<?php

namespace App\Filament\Resources\RejectedFlights;

use App\Filament\Resources\Flights\FlightResource;
use App\Filament\Resources\RejectedFlights\Pages\EditRejectedFlight;
use App\Filament\Resources\RejectedFlights\Pages\ListRejectedFlights;
use Illuminate\Database\Eloquent\Builder;

class RejectedFlightResource extends FlightResource
{
    protected static ?string $navigationLabel = 'Rejected';

    protected static ?string $modelLabel = 'rejected flight plan';

    protected static ?string $pluralModelLabel = 'rejected flight plans';

    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        if (! static::hasStatusColumn()) {
            return static::getFlightPlanBaseQuery()->whereRaw('1 = 0');
        }

        return static::getFlightPlanBaseQuery()->rejected();
    }

    public static function getNavigationBadge(): ?string
    {
        return null;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRejectedFlights::route('/'),
            'edit' => EditRejectedFlight::route('/{record}/edit'),
        ];
    }
}
