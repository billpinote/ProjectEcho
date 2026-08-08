<?php
namespace App\Filament\Panels\Artisan\Resources\AllFlightPlans\Pages;
use App\Filament\Panels\Artisan\Resources\AllFlightPlans\AllFlightResource;
use App\Filament\Shared\Resources\AllFlightPlans\Pages\EditAllFlight as SharedEditAllFlight;
class EditAllFlight extends SharedEditAllFlight
{
    protected static string $resource = AllFlightResource::class;
}