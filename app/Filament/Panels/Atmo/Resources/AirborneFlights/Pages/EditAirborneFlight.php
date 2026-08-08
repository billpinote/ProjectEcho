<?php
namespace App\Filament\Panels\Atmo\Resources\AirborneFlights\Pages;
use App\Filament\Panels\Atmo\Resources\AirborneFlights\AirborneFlightResource;
use App\Filament\Shared\Resources\AirborneFlights\Pages\EditAirborneFlight as SharedEditAirborneFlight;
class EditAirborneFlight extends SharedEditAirborneFlight
{
    protected static string $resource = AirborneFlightResource::class;
}