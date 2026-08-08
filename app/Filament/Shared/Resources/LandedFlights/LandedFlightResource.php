<?php

namespace App\Filament\Shared\Resources\LandedFlights;

use App\Filament\Shared\Resources\Flights\FlightResource;
use App\Filament\Shared\Resources\LandedFlights\Pages\EditLandedFlight;
use App\Filament\Shared\Resources\LandedFlights\Pages\ListLandedFlights;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class LandedFlightResource extends FlightResource
{
    protected static ?string $navigationParentItem = null;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownCircle;

    protected static ?string $navigationLabel = 'Landed Flights';

    protected static ?string $modelLabel = 'landed flight';

    protected static ?string $pluralModelLabel = 'landed flights';

    protected static ?int $navigationSort = 5;

    public static function getEloquentQuery(): Builder
    {
        return static::getFlightPlanBaseQuery()->landed();
    }

    public static function getNavigationBadge(): ?string
    {
        return null;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLandedFlights::route('/'),
            'edit' => EditLandedFlight::route('/{record}/edit'),
        ];
    }
}
