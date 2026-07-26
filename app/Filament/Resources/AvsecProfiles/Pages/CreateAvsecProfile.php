<?php

namespace App\Filament\Resources\AvsecProfiles\Pages;

use App\Filament\Resources\AvsecProfiles\AvsecProfileResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;

class CreateAvsecProfile extends CreateRecord
{
    protected static string $resource = AvsecProfileResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    public static bool $formActionsAreSticky = false;

    public static string|Alignment $formActionsAlignment = Alignment::End;

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Create AVSEC Profile');
    }
}
