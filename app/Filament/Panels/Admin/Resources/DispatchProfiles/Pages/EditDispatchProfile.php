<?php
namespace App\Filament\Panels\Admin\Resources\DispatchProfiles\Pages;
use App\Filament\Panels\Admin\Resources\DispatchProfiles\DispatchProfileResource;
use App\Filament\Shared\Resources\DispatchProfiles\Pages\EditDispatchProfile as SharedEditDispatchProfile;
class EditDispatchProfile extends SharedEditDispatchProfile
{
    protected static string $resource = DispatchProfileResource::class;
}