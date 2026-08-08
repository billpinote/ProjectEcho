<?php
namespace App\Filament\Panels\Artisan\Resources\Flights\Pages;
use App\Filament\Panels\Artisan\Resources\Flights\FlightResource;
use App\Filament\Shared\Resources\Flights\Pages\EditFlight as SharedEditFlight;
class EditFlight extends SharedEditFlight
{
    protected static string $resource = FlightResource::class;
}