<?php
namespace App\Filament\Panels\Admin\Resources\AtcProfiles\Pages;
use App\Filament\Panels\Admin\Resources\AtcProfiles\AtcProfileResource;
use App\Filament\Shared\Resources\AtcProfiles\Pages\EditAtcProfile as SharedEditAtcProfile;
class EditAtcProfile extends SharedEditAtcProfile
{
    protected static string $resource = AtcProfileResource::class;
}