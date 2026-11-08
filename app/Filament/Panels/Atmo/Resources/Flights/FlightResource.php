<?php

namespace App\Filament\Panels\Atmo\Resources\Flights;

use App\Domain\FlightPlans\Services\FlightWorkflowService;
use App\Domain\FlightPlans\Support\FlightStatusDisplay;
use App\Filament\Panels\Atmo\Resources\Flights\Pages\CreateFlight;
use App\Filament\Panels\Atmo\Resources\Flights\Pages\EditFlight;
use App\Filament\Panels\Atmo\Resources\Flights\Pages\ListFlights;
use App\Filament\Shared\Resources\Flights\FlightResource as SharedFlightResource;
use Illuminate\Database\Eloquent\Builder;

class FlightResource extends SharedFlightResource
{
    public static function getEloquentQuery(): Builder
    {
        return app(FlightWorkflowService::class)->queue(
            parent::getEloquentQuery(), FlightStatusDisplay::PENDING_ATMO,
        );
    }

    protected static function getPendingNavigationCounts(): array
    {
        $query = static::getEloquentQuery();

        return ['total' => (clone $query)->count(), 'new' => (clone $query)->whereNull('reviewed_at')->count()];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFlights::route('/'),
            'create' => CreateFlight::route('/create'),
            'edit' => EditFlight::route('/{record}/edit'),
        ];
    }
}
