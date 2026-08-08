<?php
namespace App\Filament\Panels\Artisan\Resources\Flights\Pages;
use App\Filament\Panels\Artisan\Resources\Flights\FlightResource;
use App\Filament\Shared\Resources\Flights\Pages\CreateFlight as SharedCreateFlight;
class CreateFlight extends SharedCreateFlight
{
    protected static string $resource = FlightResource::class;
}