<?php
namespace App\Filament\Panels\Artisan\Resources\AirborneFlights\Pages;
use App\Filament\Panels\Artisan\Resources\AirborneFlights\AirborneFlightResource;
use App\Filament\Shared\Resources\AirborneFlights\Pages\ListAirborneFlights as SharedListAirborneFlights;
class ListAirborneFlights extends SharedListAirborneFlights
{
    protected static string $resource = AirborneFlightResource::class;
}