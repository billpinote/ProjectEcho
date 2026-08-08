<?php
namespace App\Filament\Panels\Avsec\Resources\AllFlightPlans\Pages;
use App\Filament\Panels\Avsec\Resources\AllFlightPlans\AllFlightResource;
use App\Filament\Shared\Resources\AllFlightPlans\Pages\ListAllFlights as SharedListAllFlights;
class ListAllFlights extends SharedListAllFlights
{
    protected static string $resource = AllFlightResource::class;
}