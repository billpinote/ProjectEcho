<?php

namespace App\Filament\Resources\CompletedFlights;

use App\Filament\Resources\CompletedFlights\Pages\EditCompletedFlight;
use App\Filament\Resources\CompletedFlights\Pages\ListCompletedFlights;
use App\Filament\Resources\Flights\FlightResource;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class CompletedFlightResource extends FlightResource
{
    protected static ?string $navigationParentItem = 'Flights';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckBadge;

    protected static ?string $navigationLabel = 'Completed';

    protected static ?string $modelLabel = 'completed flight';

    protected static ?string $pluralModelLabel = 'completed flights';

    protected static ?int $navigationSort = 5;

    public static function getEloquentQuery(): Builder
    {
        return static::getFlightPlanBaseQuery()->completed();
    }

    public static function getNavigationBadge(): ?string
    {
        return null;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCompletedFlights::route('/'),
            'edit' => EditCompletedFlight::route('/{record}/edit'),
        ];
    }
}
