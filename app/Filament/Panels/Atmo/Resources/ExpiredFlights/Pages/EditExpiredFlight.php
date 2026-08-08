<?php
namespace App\Filament\Panels\Atmo\Resources\ExpiredFlights\Pages;
use App\Filament\Panels\Atmo\Resources\ExpiredFlights\ExpiredFlightResource;
use App\Filament\Shared\Resources\ExpiredFlights\Pages\EditExpiredFlight as SharedEditExpiredFlight;
class EditExpiredFlight extends SharedEditExpiredFlight
{
    protected static string $resource = ExpiredFlightResource::class;
}