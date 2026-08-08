<?php

namespace App\Filament\Shared\Resources\AtcProfiles\Pages;

use App\Filament\Shared\Resources\AtcProfiles\AtcProfileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAtcProfiles extends ListRecords
{
    protected static string $resource = AtcProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Create ATC Profile'),
        ];
    }
}
