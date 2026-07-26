<?php

namespace App\Filament\Resources\AtcProfiles\Pages;

use App\Filament\Resources\AtcProfiles\AtcProfileResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;

class CreateAtcProfile extends CreateRecord
{
    protected static string $resource = AtcProfileResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    public static bool $formActionsAreSticky = false;

    public static string|Alignment $formActionsAlignment = Alignment::End;

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Create ATC Profile');
    }
}
