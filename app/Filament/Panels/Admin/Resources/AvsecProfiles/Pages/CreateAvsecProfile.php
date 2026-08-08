<?php
namespace App\Filament\Panels\Admin\Resources\AvsecProfiles\Pages;
use App\Filament\Panels\Admin\Resources\AvsecProfiles\AvsecProfileResource;
use App\Filament\Shared\Resources\AvsecProfiles\Pages\CreateAvsecProfile as SharedCreateAvsecProfile;
class CreateAvsecProfile extends SharedCreateAvsecProfile
{
    protected static string $resource = AvsecProfileResource::class;
}