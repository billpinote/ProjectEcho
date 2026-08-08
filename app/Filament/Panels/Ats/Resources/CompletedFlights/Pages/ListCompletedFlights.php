<?php
namespace App\Filament\Panels\Ats\Resources\CompletedFlights\Pages;
use App\Filament\Panels\Ats\Resources\CompletedFlights\CompletedFlightResource;
use App\Filament\Shared\Resources\CompletedFlights\Pages\ListCompletedFlights as SharedListCompletedFlights;
class ListCompletedFlights extends SharedListCompletedFlights
{
    protected static string $resource = CompletedFlightResource::class;
}