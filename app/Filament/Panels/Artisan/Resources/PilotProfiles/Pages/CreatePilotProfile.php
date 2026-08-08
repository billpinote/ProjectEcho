<?php
namespace App\Filament\Panels\Artisan\Resources\PilotProfiles\Pages;
use App\Filament\Panels\Artisan\Resources\PilotProfiles\PilotProfileResource;
use App\Filament\Shared\Resources\PilotProfiles\Pages\CreatePilotProfile as SharedCreatePilotProfile;
class CreatePilotProfile extends SharedCreatePilotProfile
{
    protected static string $resource = PilotProfileResource::class;
}