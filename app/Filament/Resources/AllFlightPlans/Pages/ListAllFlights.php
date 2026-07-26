<?php

namespace App\Filament\Resources\AllFlightPlans\Pages;

use App\Filament\Resources\AllFlightPlans\AllFlightResource;
use App\Filament\Resources\Flights\Pages\ListFlights;

class ListAllFlights extends ListFlights
{
    protected static string $resource = AllFlightResource::class;
}
