<?php
namespace App\Filament\Panels\Artisan\Resources\PilotProfiles;
use App\Filament\Shared\Resources\PilotProfiles\PilotProfileResource as SharedPilotProfileResource;
use App\Filament\Panels\Artisan\Resources\PilotProfiles\Pages\ListPilotProfiles;
use App\Filament\Panels\Artisan\Resources\PilotProfiles\Pages\CreatePilotProfile;
use App\Filament\Panels\Artisan\Resources\PilotProfiles\Pages\EditPilotProfile;
class PilotProfileResource extends SharedPilotProfileResource
{
    public static function getPages(): array
    {
        return [
            'index' => ListPilotProfiles::route('/'),
            'create' => CreatePilotProfile::route('/create'),
            'edit' => EditPilotProfile::route('/{record}/edit'),
        ];
    }
}