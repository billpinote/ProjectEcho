<?php

namespace App\Filament\Shared\Resources\DispatchProfiles\Pages;

use App\Filament\Shared\Resources\DispatchProfiles\DispatchProfileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDispatchProfiles extends ListRecords
{
    protected static string $resource = DispatchProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Create Dispatch Profile'),
        ];
    }
}
