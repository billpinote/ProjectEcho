<?php
namespace App\Filament\Panels\Ats\Resources\LandedFlights\Pages;
use App\Filament\Panels\Ats\Resources\LandedFlights\LandedFlightResource;
use App\Filament\Shared\Resources\LandedFlights\Pages\EditLandedFlight as SharedEditLandedFlight;
class EditLandedFlight extends SharedEditLandedFlight
{
    protected static string $resource = LandedFlightResource::class;
}