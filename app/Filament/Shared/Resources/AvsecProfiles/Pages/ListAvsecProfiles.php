<?php

namespace App\Filament\Shared\Resources\AvsecProfiles\Pages;

use App\Filament\Shared\Resources\AvsecProfiles\AvsecProfileResource;
use Filament\Resources\Pages\ListRecords;

class ListAvsecProfiles extends ListRecords
{
    protected static string $resource = AvsecProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
