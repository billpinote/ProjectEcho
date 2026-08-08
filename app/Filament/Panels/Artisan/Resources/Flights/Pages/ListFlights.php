<?php
namespace App\Filament\Panels\Artisan\Resources\Flights\Pages;
use App\Filament\Panels\Artisan\Resources\Flights\FlightResource;
use App\Filament\Shared\Resources\Flights\Pages\ListFlights as SharedListFlights;
class ListFlights extends SharedListFlights
{
    protected static string $resource = FlightResource::class;
}