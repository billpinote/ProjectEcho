<?php
namespace App\Filament\Panels\Artisan\Resources\ActiveFlights;
use App\Filament\Shared\Resources\ActiveFlights\ActiveFlightResource as SharedActiveFlightResource;
use App\Filament\Panels\Artisan\Resources\ActiveFlights\Pages\ListActiveFlights;
use App\Filament\Panels\Artisan\Resources\ActiveFlights\Pages\EditActiveFlight;
class ActiveFlightResource extends SharedActiveFlightResource
{
    public static function getPages(): array
    {
        return [
            'index' => ListActiveFlights::route('/'),
            'edit' => EditActiveFlight::route('/{record}/edit'),
        ];
    }
}