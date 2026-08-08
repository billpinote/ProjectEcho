<?php
namespace App\Filament\Panels\Atmo\Resources\RejectedFlights\Pages;
use App\Filament\Panels\Atmo\Resources\RejectedFlights\RejectedFlightResource;
use App\Filament\Shared\Resources\RejectedFlights\Pages\EditRejectedFlight as SharedEditRejectedFlight;
class EditRejectedFlight extends SharedEditRejectedFlight
{
    protected static string $resource = RejectedFlightResource::class;
}