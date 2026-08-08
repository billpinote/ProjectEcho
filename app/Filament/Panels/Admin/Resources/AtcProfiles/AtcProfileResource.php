<?php
namespace App\Filament\Panels\Admin\Resources\AtcProfiles;
use App\Filament\Shared\Resources\AtcProfiles\AtcProfileResource as SharedAtcProfileResource;
use App\Filament\Panels\Admin\Resources\AtcProfiles\Pages\ListAtcProfiles;
use App\Filament\Panels\Admin\Resources\AtcProfiles\Pages\CreateAtcProfile;
use App\Filament\Panels\Admin\Resources\AtcProfiles\Pages\EditAtcProfile;
class AtcProfileResource extends SharedAtcProfileResource
{
    public static function getPages(): array
    {
        return [
            'index' => ListAtcProfiles::route('/'),
            'create' => CreateAtcProfile::route('/create'),
            'edit' => EditAtcProfile::route('/{record}/edit'),
        ];
    }
}