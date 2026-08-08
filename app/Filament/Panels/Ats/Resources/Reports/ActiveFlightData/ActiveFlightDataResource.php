<?php
namespace App\Filament\Panels\Ats\Resources\Reports\ActiveFlightData;
use App\Filament\Shared\Resources\Reports\ActiveFlightDataResource as SharedActiveFlightDataResource;
use App\Filament\Panels\Ats\Resources\Reports\ActiveFlightData\Pages\ListActiveFlightData;
class ActiveFlightDataResource extends SharedActiveFlightDataResource
{
    public static function getPages(): array
    {
        return [
            'index' => ListActiveFlightData::route('/'),
        ];
    }
}