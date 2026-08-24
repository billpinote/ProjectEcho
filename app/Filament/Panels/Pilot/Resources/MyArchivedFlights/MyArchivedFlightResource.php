<?php

namespace App\Filament\Panels\Pilot\Resources\MyArchivedFlights;

use App\Filament\Panels\Pilot\Resources\MyArchivedFlights\Pages\ListMyArchivedFlights;
use App\Filament\Panels\Pilot\Resources\MyFlightPlans\MyFlightPlanResource;
use App\Domain\FlightPlans\Support\FlightAccess;
use App\Models\Flight;
use Illuminate\Support\Facades\Auth;
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
        return FlightAccess::restrictQueryToPilotInvolvement(
            Flight::query(),
            Auth::user(),
            includePicAuthorizationDeclineActor: true,
        )->archivedForPilot();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMyArchivedFlights::route('/'),
        ];
    }
}
