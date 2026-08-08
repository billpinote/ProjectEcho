<?php
namespace App\Filament\Panels\Artisan\Resources\AvsecProfiles\Pages;
use App\Filament\Panels\Artisan\Resources\AvsecProfiles\AvsecProfileResource;
use App\Filament\Shared\Resources\AvsecProfiles\Pages\EditAvsecProfile as SharedEditAvsecProfile;
class EditAvsecProfile extends SharedEditAvsecProfile
{
    protected static string $resource = AvsecProfileResource::class;
}