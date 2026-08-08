<?php
namespace App\Filament\Panels\Atmo\Resources\AcceptedFlights\Pages;
use App\Filament\Panels\Atmo\Resources\AcceptedFlights\AcceptedFlightResource;
use App\Filament\Shared\Resources\AcceptedFlights\Pages\ListAcceptedFlights as SharedListAcceptedFlights;
class ListAcceptedFlights extends SharedListAcceptedFlights
{
    protected static string $resource = AcceptedFlightResource::class;
}