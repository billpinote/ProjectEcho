<?php
namespace App\Filament\Panels\Admin\Resources\DispatchProfiles\Pages;
use App\Filament\Panels\Admin\Resources\DispatchProfiles\DispatchProfileResource;
use App\Filament\Shared\Resources\DispatchProfiles\Pages\ListDispatchProfiles as SharedListDispatchProfiles;
class ListDispatchProfiles extends SharedListDispatchProfiles
{
    protected static string $resource = DispatchProfileResource::class;
}