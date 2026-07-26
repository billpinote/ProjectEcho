<?php

namespace App\Filament\Resources\PilotProfiles\Pages;

use App\Filament\Resources\PilotProfiles\PilotProfileResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;

class CreatePilotProfile extends CreateRecord
{
    protected static string $resource = PilotProfileResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    public static bool $formActionsAreSticky = false;

    public static string|Alignment $formActionsAlignment = Alignment::End;

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Create Pilot Profile');
    }
}
