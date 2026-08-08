<?php
namespace App\Filament\Panels\Dispatch\Resources\AcceptedFlights\Pages;
use App\Filament\Panels\Dispatch\Resources\AcceptedFlights\AcceptedFlightResource;
use App\Filament\Shared\Resources\AcceptedFlights\Pages\ListAcceptedFlights as SharedListAcceptedFlights;
class ListAcceptedFlights extends SharedListAcceptedFlights
{
    protected static string $resource = AcceptedFlightResource::class;
}