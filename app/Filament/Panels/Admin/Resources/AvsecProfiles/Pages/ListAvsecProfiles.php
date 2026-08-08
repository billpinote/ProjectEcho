<?php
namespace App\Filament\Panels\Admin\Resources\AvsecProfiles\Pages;
use App\Filament\Panels\Admin\Resources\AvsecProfiles\AvsecProfileResource;
use App\Filament\Shared\Resources\AvsecProfiles\Pages\ListAvsecProfiles as SharedListAvsecProfiles;
class ListAvsecProfiles extends SharedListAvsecProfiles
{
    protected static string $resource = AvsecProfileResource::class;
}