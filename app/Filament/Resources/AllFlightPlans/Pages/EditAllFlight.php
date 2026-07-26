<?php

namespace App\Filament\Resources\AllFlightPlans\Pages;

use App\Filament\Resources\AllFlightPlans\AllFlightResource;
use App\Filament\Resources\Flights\Pages\EditFlight;

class EditAllFlight extends EditFlight
{
    protected static string $resource = AllFlightResource::class;
}
