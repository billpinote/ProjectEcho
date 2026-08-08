<?php

namespace App\Filament\Shared\Resources\PilotProfiles\Pages;

use App\Filament\Shared\Resources\PilotProfiles\PilotProfileResource;
use Filament\Resources\Pages\ListRecords;

class ListPilotProfiles extends ListRecords
{
    protected static string $resource = PilotProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
