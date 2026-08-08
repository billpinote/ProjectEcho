<?php
namespace App\Filament\Panels\Ats\Resources\AcceptedFlights\Pages;
use App\Filament\Panels\Ats\Resources\AcceptedFlights\AcceptedFlightResource;
use App\Filament\Shared\Resources\AcceptedFlights\Pages\ListAcceptedFlights as SharedListAcceptedFlights;
class ListAcceptedFlights extends SharedListAcceptedFlights
{
    protected static string $resource = AcceptedFlightResource::class;
}