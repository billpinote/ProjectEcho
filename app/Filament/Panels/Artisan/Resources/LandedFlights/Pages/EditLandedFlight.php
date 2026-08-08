<?php
namespace App\Filament\Panels\Artisan\Resources\LandedFlights\Pages;
use App\Filament\Panels\Artisan\Resources\LandedFlights\LandedFlightResource;
use App\Filament\Shared\Resources\LandedFlights\Pages\EditLandedFlight as SharedEditLandedFlight;
class EditLandedFlight extends SharedEditLandedFlight
{
    protected static string $resource = LandedFlightResource::class;
}