<?php

namespace App\Filament\Panels\Dispatch\Resources\Flights\Pages;

use App\Filament\Panels\Dispatch\Resources\Flights\FlightResource;
use App\Filament\Shared\Resources\Flights\Pages\EditFlight as SharedEditFlight;
use App\Models\Flight;

class EditFlight extends SharedEditFlight
{
    protected static string $resource = FlightResource::class;

    public static function authorizeResourceAccess(): void
    {
        abort_unless(auth()->user()?->can('viewAny', Flight::class), 403);
    }
}
