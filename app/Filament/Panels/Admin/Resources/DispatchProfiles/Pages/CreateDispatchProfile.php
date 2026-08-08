<?php
namespace App\Filament\Panels\Admin\Resources\DispatchProfiles\Pages;
use App\Filament\Panels\Admin\Resources\DispatchProfiles\DispatchProfileResource;
use App\Filament\Shared\Resources\DispatchProfiles\Pages\CreateDispatchProfile as SharedCreateDispatchProfile;
class CreateDispatchProfile extends SharedCreateDispatchProfile
{
    protected static string $resource = DispatchProfileResource::class;
}