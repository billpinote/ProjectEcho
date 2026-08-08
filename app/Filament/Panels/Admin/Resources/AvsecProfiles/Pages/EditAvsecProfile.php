<?php
namespace App\Filament\Panels\Admin\Resources\AvsecProfiles\Pages;
use App\Filament\Panels\Admin\Resources\AvsecProfiles\AvsecProfileResource;
use App\Filament\Shared\Resources\AvsecProfiles\Pages\EditAvsecProfile as SharedEditAvsecProfile;
class EditAvsecProfile extends SharedEditAvsecProfile
{
    protected static string $resource = AvsecProfileResource::class;
}