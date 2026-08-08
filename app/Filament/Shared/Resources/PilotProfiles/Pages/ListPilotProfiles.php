<?php

namespace App\Filament\Shared\Resources\PilotProfiles\Pages;

use App\Filament\Shared\Resources\PilotProfiles\PilotProfileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPilotProfiles extends ListRecords
{
    protected static string $resource = PilotProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Create Pilot Profile'),
        ];
    }
}
