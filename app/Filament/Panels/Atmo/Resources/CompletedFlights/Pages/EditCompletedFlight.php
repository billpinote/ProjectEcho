<?php
namespace App\Filament\Panels\Atmo\Resources\CompletedFlights\Pages;
use App\Filament\Panels\Atmo\Resources\CompletedFlights\CompletedFlightResource;
use App\Filament\Shared\Resources\CompletedFlights\Pages\EditCompletedFlight as SharedEditCompletedFlight;
class EditCompletedFlight extends SharedEditCompletedFlight
{
    protected static string $resource = CompletedFlightResource::class;
}