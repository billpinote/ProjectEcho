<?php
namespace App\Filament\Panels\Ats\Resources\ActiveFlights\Pages;
use App\Filament\Panels\Ats\Resources\ActiveFlights\ActiveFlightResource;
use App\Filament\Shared\Resources\ActiveFlights\Pages\ListActiveFlights as SharedListActiveFlights;
class ListActiveFlights extends SharedListActiveFlights
{
    protected static string $resource = ActiveFlightResource::class;
}