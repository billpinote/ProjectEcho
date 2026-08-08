<?php
namespace App\Filament\Panels\Artisan\Resources\RejectedFlights\Pages;
use App\Filament\Panels\Artisan\Resources\RejectedFlights\RejectedFlightResource;
use App\Filament\Shared\Resources\RejectedFlights\Pages\ListRejectedFlights as SharedListRejectedFlights;
class ListRejectedFlights extends SharedListRejectedFlights
{
    protected static string $resource = RejectedFlightResource::class;
}