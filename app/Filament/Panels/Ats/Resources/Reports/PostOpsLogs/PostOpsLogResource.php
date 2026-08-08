<?php
namespace App\Filament\Panels\Ats\Resources\Reports\PostOpsLogs;
use App\Filament\Shared\Resources\Reports\PostOpsLogResource as SharedPostOpsLogResource;
use App\Filament\Panels\Ats\Resources\Reports\PostOpsLogs\Pages\ListPostOpsLogs;
class PostOpsLogResource extends SharedPostOpsLogResource
{
    public static function getPages(): array
    {
        return [
            'index' => ListPostOpsLogs::route('/'),
        ];
    }
}