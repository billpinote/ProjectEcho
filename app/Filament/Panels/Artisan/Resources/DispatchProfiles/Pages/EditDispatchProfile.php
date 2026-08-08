<?php
namespace App\Filament\Panels\Artisan\Resources\DispatchProfiles\Pages;
use App\Filament\Panels\Artisan\Resources\DispatchProfiles\DispatchProfileResource;
use App\Filament\Shared\Resources\DispatchProfiles\Pages\EditDispatchProfile as SharedEditDispatchProfile;
class EditDispatchProfile extends SharedEditDispatchProfile
{
    protected static string $resource = DispatchProfileResource::class;
}