<?php

namespace App\Filament\Resources\DispatchProfiles\Pages;

use App\Filament\Resources\DispatchProfiles\DispatchProfileResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;

class CreateDispatchProfile extends CreateRecord
{
    protected static string $resource = DispatchProfileResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    public static bool $formActionsAreSticky = false;

    public static string|Alignment $formActionsAlignment = Alignment::End;

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Create Dispatch Profile');
    }
}
