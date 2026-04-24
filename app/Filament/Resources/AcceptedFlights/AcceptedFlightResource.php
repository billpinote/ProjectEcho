<?php

namespace App\Filament\Resources\AcceptedFlights;

use App\Filament\Resources\AcceptedFlights\Pages\EditAcceptedFlight;
use App\Filament\Resources\AcceptedFlights\Pages\ListAcceptedFlights;
use App\Filament\Resources\Flights\FlightResource;
use Illuminate\Database\Eloquent\Builder;
use Filament\Support\Icons\Heroicon;

class AcceptedFlightResource extends FlightResource
{
    protected static ?string $navigationParentItem = 'Flights';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckCircle;

    protected static ?string $navigationLabel = 'Ready';

    protected static ?string $modelLabel = 'ready flight';

    protected static ?string $pluralModelLabel = 'ready flights';

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        if (! static::hasStatusColumn()) {
            return static::getFlightPlanBaseQuery()->whereNotNull('accepted_by_user_id');
        }

        return static::getFlightPlanBaseQuery()->ready();
    }

    public static function getNavigationBadge(): ?string
    {
        return null;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAcceptedFlights::route('/'),
            'edit' => EditAcceptedFlight::route('/{record}/edit'),
        ];
    }
}
