<?php
namespace App\Filament\Panels\Atmo\Resources\ActiveFlights\Pages;
use App\Filament\Panels\Atmo\Resources\ActiveFlights\ActiveFlightResource;
use App\Filament\Shared\Resources\ActiveFlights\Pages\ListActiveFlights as SharedListActiveFlights;
class ListActiveFlights extends SharedListActiveFlights
{
    protected static string $resource = ActiveFlightResource::class;
}