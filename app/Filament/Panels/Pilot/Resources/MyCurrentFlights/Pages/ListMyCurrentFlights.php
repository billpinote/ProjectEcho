<?php

namespace App\Filament\Panels\Pilot\Resources\MyCurrentFlights\Pages;

use App\Domain\FlightPlans\Support\FlightStatusDisplay;
use App\Filament\Panels\Pilot\Resources\MyCurrentFlights\MyCurrentFlightResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListMyCurrentFlights extends ListRecords
{
    protected static string $resource = MyCurrentFlightResource::class;

    public function getSubheading(): ?string
    {
        return MyCurrentFlightResource::getEloquentQuery()->ready()->exists()
            ? FlightStatusDisplay::ACCEPTANCE_NOTICE : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createFlightPlan')
                ->label('Create Flight Plan')
                ->icon('heroicon-o-plus')
                ->url(fn (): string => route('filament.pilot.resources.flights.create')),
        ];
    }
}
