<?php

namespace App\Filament\Resources\AirborneFlights;

use App\Filament\Resources\AirborneFlights\Pages\EditAirborneFlight;
use App\Filament\Resources\AirborneFlights\Pages\ListAirborneFlights;
use App\Filament\Resources\Flights\FlightResource;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class AirborneFlightResource extends FlightResource
{
    protected static ?string $navigationParentItem = 'Flights';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static ?string $navigationLabel = 'Airborne';

    protected static ?string $modelLabel = 'airborne flight';

    protected static ?string $pluralModelLabel = 'airborne flights';

    protected static ?int $navigationSort = 3;

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
