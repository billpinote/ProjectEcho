<?php
namespace App\Filament\Panels\Admin\Resources\PilotProfiles\Pages;
use App\Filament\Panels\Admin\Resources\PilotProfiles\PilotProfileResource;
use App\Filament\Shared\Resources\PilotProfiles\Pages\EditPilotProfile as SharedEditPilotProfile;
class EditPilotProfile extends SharedEditPilotProfile
{
    protected static string $resource = PilotProfileResource::class;
}