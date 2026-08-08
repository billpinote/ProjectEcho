<?php

namespace App\Filament\Shared\Resources\AllFlightPlans\Pages;

use App\Filament\Shared\Resources\AllFlightPlans\AllFlightResource;
use App\Filament\Shared\Resources\Flights\Pages\ListFlights;

class ListAllFlights extends ListFlights
{
    protected static string $resource = AllFlightResource::class;
}
