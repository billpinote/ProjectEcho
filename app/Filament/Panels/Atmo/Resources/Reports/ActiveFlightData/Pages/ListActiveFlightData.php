<?php
namespace App\Filament\Panels\Atmo\Resources\Reports\ActiveFlightData\Pages;
use App\Filament\Panels\Atmo\Resources\Reports\ActiveFlightData\ActiveFlightDataResource;
use App\Filament\Shared\Resources\Reports\Pages\ListActiveFlightData as SharedListActiveFlightData;
class ListActiveFlightData extends SharedListActiveFlightData
{
    protected static string $resource = ActiveFlightDataResource::class;
}