<?php
namespace App\Filament\Panels\Ats\Resources\AcceptedFlights\Pages;
use App\Filament\Panels\Ats\Resources\AcceptedFlights\AcceptedFlightResource;
use App\Filament\Shared\Resources\AcceptedFlights\Pages\EditAcceptedFlight as SharedEditAcceptedFlight;
class EditAcceptedFlight extends SharedEditAcceptedFlight
{
    protected static string $resource = AcceptedFlightResource::class;
}