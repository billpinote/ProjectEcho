<?php

namespace App\Filament\Resources\ActiveFlights;

use App\Filament\Resources\ActiveFlights\Pages\EditActiveFlight;
use App\Filament\Resources\ActiveFlights\Pages\ListActiveFlights;
use App\Filament\Resources\Flights\FlightResource;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ActiveFlightResource extends FlightResource
{
    protected static ?string $navigationParentItem = 'Flights';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPlayCircle;

    protected static ?string $navigationLabel = 'Active';

    protected static ?string $modelLabel = 'active flight';

    protected static ?string $pluralModelLabel = 'active flights';

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return static::getFlightPlanBaseQuery()->active();
    }

    public static function getNavigationBadge(): ?string
    {
        return null;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActiveFlights::route('/'),
            'edit' => EditActiveFlight::route('/{record}/edit'),
        ];
    }
}
