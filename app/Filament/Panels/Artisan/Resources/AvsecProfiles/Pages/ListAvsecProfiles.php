<?php
namespace App\Filament\Panels\Artisan\Resources\AvsecProfiles\Pages;
use App\Filament\Panels\Artisan\Resources\AvsecProfiles\AvsecProfileResource;
use App\Filament\Shared\Resources\AvsecProfiles\Pages\ListAvsecProfiles as SharedListAvsecProfiles;
class ListAvsecProfiles extends SharedListAvsecProfiles
{
    protected static string $resource = AvsecProfileResource::class;
}