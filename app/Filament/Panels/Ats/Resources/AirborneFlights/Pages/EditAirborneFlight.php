<?php
namespace App\Filament\Panels\Ats\Resources\AirborneFlights\Pages;
use App\Filament\Panels\Ats\Resources\AirborneFlights\AirborneFlightResource;
use App\Filament\Shared\Resources\AirborneFlights\Pages\EditAirborneFlight as SharedEditAirborneFlight;
class EditAirborneFlight extends SharedEditAirborneFlight
{
    protected static string $resource = AirborneFlightResource::class;
}