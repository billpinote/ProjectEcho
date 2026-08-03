<?php

namespace App\Filament\Resources\MyFlightPlans\Pages;

use App\Filament\Resources\MyFlightPlans\MyFlightPlansResource;
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
                ->url(fn (): string => \App\Filament\Resources\Flights\Pages\CreateFlight::getUrl()),
        ];
    }
}
