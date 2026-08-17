<?php

namespace App\Filament\Panels\Pilot\Resources\AwaitingAuthorizationFlights;

use App\Filament\Panels\Pilot\Resources\AwaitingAuthorizationFlights\Pages\ListAwaitingAuthorizationFlights;
use App\Filament\Panels\Pilot\Resources\MyFlightPlans\MyFlightPlanResource;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class AwaitingAuthorizationFlightResource extends MyFlightPlanResource
{
    protected static ?string $slug = 'awaiting-authorization-flights';

    protected static string|\UnitEnum|null $navigationGroup = 'PIC Authorization';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static ?string $navigationLabel = 'Awaiting Authorization';

    protected static ?string $modelLabel = 'flight plan awaiting authorization';

    protected static ?string $pluralModelLabel = 'flight plans awaiting authorization';

    protected static ?int $navigationSort = 23;

    public static function getEloquentQuery(): Builder
    {
        return static::getOwnedFlightQuery()->awaitingPicAuthorization();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAwaitingAuthorizationFlights::route('/'),
        ];
    }
}
