<?php

namespace App\Filament\Resources\AvsecProfiles\Pages;

use App\Filament\Resources\AvsecProfiles\AvsecProfileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAvsecProfiles extends ListRecords
{
    protected static string $resource = AvsecProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Create AVSEC Profile'),
        ];
    }
}
