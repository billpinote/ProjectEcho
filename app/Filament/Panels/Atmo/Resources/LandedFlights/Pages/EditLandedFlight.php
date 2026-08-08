<?php
namespace App\Filament\Panels\Atmo\Resources\LandedFlights\Pages;
use App\Filament\Panels\Atmo\Resources\LandedFlights\LandedFlightResource;
use App\Filament\Shared\Resources\LandedFlights\Pages\EditLandedFlight as SharedEditLandedFlight;
class EditLandedFlight extends SharedEditLandedFlight
{
    protected static string $resource = LandedFlightResource::class;
}