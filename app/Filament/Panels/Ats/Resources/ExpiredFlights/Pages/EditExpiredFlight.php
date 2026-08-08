<?php
namespace App\Filament\Panels\Ats\Resources\ExpiredFlights\Pages;
use App\Filament\Panels\Ats\Resources\ExpiredFlights\ExpiredFlightResource;
use App\Filament\Shared\Resources\ExpiredFlights\Pages\EditExpiredFlight as SharedEditExpiredFlight;
class EditExpiredFlight extends SharedEditExpiredFlight
{
    protected static string $resource = ExpiredFlightResource::class;
}