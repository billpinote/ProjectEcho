<?php
namespace App\Filament\Panels\Admin\Resources\PilotProfiles\Pages;
use App\Filament\Panels\Admin\Resources\PilotProfiles\PilotProfileResource;
use App\Filament\Shared\Resources\PilotProfiles\Pages\ListPilotProfiles as SharedListPilotProfiles;
class ListPilotProfiles extends SharedListPilotProfiles
{
    protected static string $resource = PilotProfileResource::class;
}