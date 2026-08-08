<?php
namespace App\Filament\Panels\Artisan\Resources\AtcProfiles\Pages;
use App\Filament\Panels\Artisan\Resources\AtcProfiles\AtcProfileResource;
use App\Filament\Shared\Resources\AtcProfiles\Pages\ListAtcProfiles as SharedListAtcProfiles;
class ListAtcProfiles extends SharedListAtcProfiles
{
    protected static string $resource = AtcProfileResource::class;
}