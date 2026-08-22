<?php

namespace App\Filament\Panels\Pilot\Resources\MyCurrentFlights;

use App\Filament\Panels\Pilot\Resources\MyCurrentFlights\Pages\ListMyCurrentFlights;
use App\Filament\Panels\Pilot\Resources\MyFlightPlans\MyFlightPlanResource;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class MyCurrentFlightResource extends MyFlightPlanResource
{
    protected static ?string $slug = 'my-current-flights';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static ?string $navigationLabel = 'Current';

    protected static ?string $modelLabel = 'current flight plan';

    protected static ?string $pluralModelLabel = 'current flight plans';

    protected static ?int $navigationSort = 20;

    public static function getEloquentQuery(): Builder
    {
        return static::getInvolvedFlightQuery()->currentForPilot();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMyCurrentFlights::route('/'),
        ];
    }
}
