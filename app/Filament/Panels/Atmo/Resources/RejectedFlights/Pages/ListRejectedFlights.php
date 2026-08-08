<?php
namespace App\Filament\Panels\Atmo\Resources\RejectedFlights\Pages;
use App\Filament\Panels\Atmo\Resources\RejectedFlights\RejectedFlightResource;
use App\Filament\Shared\Resources\RejectedFlights\Pages\ListRejectedFlights as SharedListRejectedFlights;
class ListRejectedFlights extends SharedListRejectedFlights
{
    protected static string $resource = RejectedFlightResource::class;
}