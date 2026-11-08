<?php

namespace App\Filament\Panels\Atmo\Resources\AwaitingAvsecFlights\Pages;

use App\Filament\Panels\Atmo\Resources\AwaitingAvsecFlights\AwaitingAvsecFlightResource;
use Filament\Resources\Pages\ListRecords;

class ListAwaitingAvsecFlights extends ListRecords
{
    protected static string $resource = AwaitingAvsecFlightResource::class;
}
