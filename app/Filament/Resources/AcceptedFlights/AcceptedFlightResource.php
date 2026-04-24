<?php

namespace App\Filament\Resources\AcceptedFlights;

use App\Filament\Resources\AcceptedFlights\Pages\EditAcceptedFlight;
use App\Filament\Resources\AcceptedFlights\Pages\ListAcceptedFlights;
use App\Filament\Resources\Flights\FlightResource;
use Illuminate\Database\Eloquent\Builder;
use Filament\Support\Icons\Heroicon;

class AcceptedFlightResource extends FlightResource
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckCircle;

    protected static ?string $navigationLabel = 'Accepted';

    protected static ?string $modelLabel = 'accepted flight plan';

    protected static ?string $pluralModelLabel = 'accepted flight plans';

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        if (! static::hasStatusColumn()) {
            return static::getFlightPlanBaseQuery()->whereNotNull('accepted_by_user_id');
        }

        return static::getFlightPlanBaseQuery()->accepted();
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
