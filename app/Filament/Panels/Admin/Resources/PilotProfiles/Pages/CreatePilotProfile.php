<?php
namespace App\Filament\Panels\Admin\Resources\PilotProfiles\Pages;
use App\Filament\Panels\Admin\Resources\PilotProfiles\PilotProfileResource;
use App\Filament\Shared\Resources\PilotProfiles\Pages\CreatePilotProfile as SharedCreatePilotProfile;
class CreatePilotProfile extends SharedCreatePilotProfile
{
    protected static string $resource = PilotProfileResource::class;
}