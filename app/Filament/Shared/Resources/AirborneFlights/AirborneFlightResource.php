<?php

namespace App\Filament\Shared\Resources\AirborneFlights;

use App\Filament\Shared\Resources\AirborneFlights\Pages\EditAirborneFlight;
use App\Filament\Shared\Resources\AirborneFlights\Pages\ListAirborneFlights;
use App\Filament\Shared\Resources\Flights\FlightResource;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class AirborneFlightResource extends FlightResource
{
    protected static ?string $navigationParentItem = null;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static ?string $navigationLabel = 'Airborne Flights';

    protected static ?string $modelLabel = 'airborne flight';

    protected static ?string $pluralModelLabel = 'airborne flights';

    protected static ?int $navigationSort = 4;

    public static function getEloquentQuery(): Builder
    {
        return static::getFlightPlanBaseQuery()->airborne();
    }

    public static function getNavigationBadge(): ?string
    {
        return null;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAirborneFlights::route('/'),
            'edit' => EditAirborneFlight::route('/{record}/edit'),
        ];
    }
}
