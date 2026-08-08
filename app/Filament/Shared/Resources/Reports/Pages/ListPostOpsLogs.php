<?php

namespace App\Filament\Shared\Resources\Reports\Pages;

use App\Filament\Shared\Resources\Reports\PostOpsLogResource;
use Filament\Resources\Pages\ListRecords;

class ListPostOpsLogs extends ListRecords
{
    protected static string $resource = PostOpsLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
