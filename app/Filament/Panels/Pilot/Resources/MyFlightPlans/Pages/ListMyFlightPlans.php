<?php

namespace App\Filament\Panels\Pilot\Resources\MyFlightPlans\Pages;

use App\Filament\Panels\Pilot\Resources\MyFlightPlans\MyFlightPlansResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListMyFlightPlans extends ListRecords
{
    protected static string $resource = MyFlightPlansResource::class;

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
