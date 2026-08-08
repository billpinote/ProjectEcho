<?php
namespace App\Filament\Panels\Atmo\Resources\ExpiredFlights\Pages;
use App\Filament\Panels\Atmo\Resources\ExpiredFlights\ExpiredFlightResource;
use App\Filament\Shared\Resources\ExpiredFlights\Pages\ListExpiredFlights as SharedListExpiredFlights;
class ListExpiredFlights extends SharedListExpiredFlights
{
    protected static string $resource = ExpiredFlightResource::class;
}