<?php
namespace App\Filament\Panels\Artisan\Resources\PilotProfiles\Pages;
use App\Filament\Panels\Artisan\Resources\PilotProfiles\PilotProfileResource;
use App\Filament\Shared\Resources\PilotProfiles\Pages\EditPilotProfile as SharedEditPilotProfile;
class EditPilotProfile extends SharedEditPilotProfile
{
    protected static string $resource = PilotProfileResource::class;
}