<?php

namespace App\Filament\Resources\MyCurrentFlights\Pages;

use App\Filament\Resources\MyCurrentFlights\MyCurrentFlightResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListMyCurrentFlights extends ListRecords
{
    protected static string $resource = MyCurrentFlightResource::class;

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
