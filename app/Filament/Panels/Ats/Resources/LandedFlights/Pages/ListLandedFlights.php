<?php
namespace App\Filament\Panels\Ats\Resources\LandedFlights\Pages;
use App\Filament\Panels\Ats\Resources\LandedFlights\LandedFlightResource;
use App\Filament\Shared\Resources\LandedFlights\Pages\ListLandedFlights as SharedListLandedFlights;
class ListLandedFlights extends SharedListLandedFlights
{
    protected static string $resource = LandedFlightResource::class;
}