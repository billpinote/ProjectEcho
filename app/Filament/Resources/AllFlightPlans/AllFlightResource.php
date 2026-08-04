<?php

namespace App\Filament\Resources\AllFlightPlans;

use App\Filament\Resources\Flights\FlightResource;
use App\Filament\Resources\Flights\Pages\EditFlight;
use App\Filament\Resources\Flights\Pages\ListFlights;
use App\Filament\Resources\AllFlightPlans\Pages\EditAllFlight;
use App\Filament\Resources\AllFlightPlans\Pages\ListAllFlights;
use App\Models\Flight;
use Illuminate\Database\Eloquent\Builder;
use Filament\Support\Icons\Heroicon;

class AllFlightResource extends FlightResource
{
    protected static ?string $slug = 'all-flight-plans';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedSquaresPlus;

    protected static ?string $navigationLabel = 'All Flights';

    protected static ?string $modelLabel = 'flight plan';

    protected static ?string $pluralModelLabel = 'flight plans';

    protected static ?int $navigationSort = 9;

    public static function getEloquentQuery(): Builder
    {
        if (! static::hasStatusColumn()) {
            return static::getFlightPlanBaseQuery();
        }

        return static::getFlightPlanBaseQuery()
            ->whereNot(static fn (Builder $query): Builder => $query->pendingExpired());
    }

    public static function getNavigationBadge(): ?string
    {
        return null;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAllFlights::route('/'),
            'edit' => EditAllFlight::route('/{record}/edit'),
        ];
    }
}
