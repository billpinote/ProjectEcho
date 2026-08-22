<?php

namespace App\Filament\Panels\Pilot\Resources\MyCompletedFlights;

use App\Filament\Panels\Pilot\Resources\MyCompletedFlights\Pages\ListMyCompletedFlights;
use App\Filament\Panels\Pilot\Resources\MyFlightPlans\MyFlightPlanResource;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class MyCompletedFlightResource extends MyFlightPlanResource
{
    protected static ?string $slug = 'my-completed-flights';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckBadge;

    protected static ?string $navigationLabel = 'Completed';

    protected static ?string $modelLabel = 'completed flight';

    protected static ?string $pluralModelLabel = 'completed flights';

    protected static ?int $navigationSort = 21;

    public static function getEloquentQuery(): Builder
    {
        return static::getInvolvedFlightQuery()->completed();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMyCompletedFlights::route('/'),
        ];
    }
}
