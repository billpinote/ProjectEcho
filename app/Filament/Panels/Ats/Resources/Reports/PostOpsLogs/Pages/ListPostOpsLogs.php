<?php
namespace App\Filament\Panels\Ats\Resources\Reports\PostOpsLogs\Pages;
use App\Filament\Panels\Ats\Resources\Reports\PostOpsLogs\PostOpsLogResource;
use App\Filament\Shared\Resources\Reports\Pages\ListPostOpsLogs as SharedListPostOpsLogs;
class ListPostOpsLogs extends SharedListPostOpsLogs
{
    protected static string $resource = PostOpsLogResource::class;
}