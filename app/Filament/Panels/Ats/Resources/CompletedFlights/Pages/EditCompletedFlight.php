<?php
namespace App\Filament\Panels\Ats\Resources\CompletedFlights\Pages;
use App\Filament\Panels\Ats\Resources\CompletedFlights\CompletedFlightResource;
use App\Filament\Shared\Resources\CompletedFlights\Pages\EditCompletedFlight as SharedEditCompletedFlight;
class EditCompletedFlight extends SharedEditCompletedFlight
{
    protected static string $resource = CompletedFlightResource::class;
}