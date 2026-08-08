<?php
namespace App\Filament\Panels\Artisan\Resources\DispatchProfiles;
use App\Filament\Shared\Resources\DispatchProfiles\DispatchProfileResource as SharedDispatchProfileResource;
use App\Filament\Panels\Artisan\Resources\DispatchProfiles\Pages\ListDispatchProfiles;
use App\Filament\Panels\Artisan\Resources\DispatchProfiles\Pages\CreateDispatchProfile;
use App\Filament\Panels\Artisan\Resources\DispatchProfiles\Pages\EditDispatchProfile;
class DispatchProfileResource extends SharedDispatchProfileResource
{
    public static function getPages(): array
    {
        return [
            'index' => ListDispatchProfiles::route('/'),
            'create' => CreateDispatchProfile::route('/create'),
            'edit' => EditDispatchProfile::route('/{record}/edit'),
        ];
    }
}