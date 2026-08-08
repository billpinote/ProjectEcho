<?php
namespace App\Filament\Panels\Artisan\Resources\PilotProfiles\Pages;
use App\Filament\Panels\Artisan\Resources\PilotProfiles\PilotProfileResource;
use App\Filament\Shared\Resources\PilotProfiles\Pages\ListPilotProfiles as SharedListPilotProfiles;
class ListPilotProfiles extends SharedListPilotProfiles
{
    protected static string $resource = PilotProfileResource::class;
}