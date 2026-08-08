<?php
namespace App\Filament\Panels\Artisan\Resources\AvsecProfiles\Pages;
use App\Filament\Panels\Artisan\Resources\AvsecProfiles\AvsecProfileResource;
use App\Filament\Shared\Resources\AvsecProfiles\Pages\CreateAvsecProfile as SharedCreateAvsecProfile;
class CreateAvsecProfile extends SharedCreateAvsecProfile
{
    protected static string $resource = AvsecProfileResource::class;
}