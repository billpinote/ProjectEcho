<?php

namespace App\Filament\Panels\Dispatch\Resources\Flights;

use App\Domain\FlightPlans\Enums\FlightPlanStatus;
use App\Filament\Panels\Dispatch\Resources\Flights\Pages\CreateFlight;
use App\Filament\Panels\Dispatch\Resources\Flights\Pages\EditFlight;
use App\Filament\Shared\Resources\Flights\FlightResource as SharedFlightResource;
use Illuminate\Database\Eloquent\Builder;

class FlightResource extends SharedFlightResource
{
    public static function getEloquentQuery(): Builder
    {
        return static::getFlightPlanBaseQuery()->whereIn('status', [FlightPlanStatus::AwaitingPic, FlightPlanStatus::Pending]);
    }

    public static function getPages(): array
    {
        return [
            'create' => CreateFlight::route('/create'),
            'edit' => EditFlight::route('/{record}/edit'),
        ];
    }
}
