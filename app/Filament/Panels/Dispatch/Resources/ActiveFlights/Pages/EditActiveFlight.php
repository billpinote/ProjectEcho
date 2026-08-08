<?php
namespace App\Filament\Panels\Dispatch\Resources\ActiveFlights\Pages;
use App\Filament\Panels\Dispatch\Resources\ActiveFlights\ActiveFlightResource;
use App\Filament\Shared\Resources\ActiveFlights\Pages\EditActiveFlight as SharedEditActiveFlight;
class EditActiveFlight extends SharedEditActiveFlight
{
    protected static string $resource = ActiveFlightResource::class;
}