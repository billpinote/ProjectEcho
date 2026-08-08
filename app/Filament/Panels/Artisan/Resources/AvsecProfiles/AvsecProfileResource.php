<?php
namespace App\Filament\Panels\Artisan\Resources\AvsecProfiles;
use App\Filament\Shared\Resources\AvsecProfiles\AvsecProfileResource as SharedAvsecProfileResource;
use App\Filament\Panels\Artisan\Resources\AvsecProfiles\Pages\ListAvsecProfiles;
use App\Filament\Panels\Artisan\Resources\AvsecProfiles\Pages\CreateAvsecProfile;
use App\Filament\Panels\Artisan\Resources\AvsecProfiles\Pages\EditAvsecProfile;
class AvsecProfileResource extends SharedAvsecProfileResource
{
    public static function getPages(): array
    {
        return [
            'index' => ListAvsecProfiles::route('/'),
            'create' => CreateAvsecProfile::route('/create'),
            'edit' => EditAvsecProfile::route('/{record}/edit'),
        ];
    }
}