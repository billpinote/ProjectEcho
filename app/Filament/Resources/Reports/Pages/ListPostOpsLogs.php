<?php

namespace App\Filament\Resources\Reports\Pages;

use App\Filament\Resources\Reports\PostOpsLogResource;
use Filament\Resources\Pages\ListRecords;

class ListPostOpsLogs extends ListRecords
{
    protected static string $resource = PostOpsLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
