<?php
namespace App\Filament\Panels\Atmo\Resources\AllFlightPlans\Pages;
use App\Filament\Panels\Atmo\Resources\AllFlightPlans\AllFlightResource;
use App\Filament\Shared\Resources\AllFlightPlans\Pages\EditAllFlight as SharedEditAllFlight;
class EditAllFlight extends SharedEditAllFlight
{
    protected static string $resource = AllFlightResource::class;
}