<?php

namespace App\Filament\Resources\MyArchivedFlights;

use App\Filament\Resources\MyArchivedFlights\Pages\ListMyArchivedFlights;
use App\Filament\Resources\MyFlightPlans\MyFlightPlanResource;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class MyArchivedFlightResource extends MyFlightPlanResource
{
    protected static ?string $slug = 'my-archived-flights';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?string $navigationLabel = 'Archived';

    protected static ?string $modelLabel = 'archived flight plan';

    protected static ?string $pluralModelLabel = 'archived flight plans';

    protected static ?int $navigationSort = 22;

    public static function getEloquentQuery(): Builder
    {
        return static::getOwnedFlightQuery()->archivedForPilot();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMyArchivedFlights::route('/'),
        ];
    }
}
