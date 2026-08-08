<?php
namespace App\Filament\Panels\Artisan\Resources\AtcProfiles\Pages;
use App\Filament\Panels\Artisan\Resources\AtcProfiles\AtcProfileResource;
use App\Filament\Shared\Resources\AtcProfiles\Pages\CreateAtcProfile as SharedCreateAtcProfile;
class CreateAtcProfile extends SharedCreateAtcProfile
{
    protected static string $resource = AtcProfileResource::class;
}