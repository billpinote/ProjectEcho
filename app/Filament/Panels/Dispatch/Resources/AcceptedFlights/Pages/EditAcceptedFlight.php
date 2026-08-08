<?php
namespace App\Filament\Panels\Dispatch\Resources\AcceptedFlights\Pages;
use App\Filament\Panels\Dispatch\Resources\AcceptedFlights\AcceptedFlightResource;
use App\Filament\Shared\Resources\AcceptedFlights\Pages\EditAcceptedFlight as SharedEditAcceptedFlight;
class EditAcceptedFlight extends SharedEditAcceptedFlight
{
    protected static string $resource = AcceptedFlightResource::class;
}