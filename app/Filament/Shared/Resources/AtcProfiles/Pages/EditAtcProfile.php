<?php

namespace App\Filament\Shared\Resources\AtcProfiles\Pages;

use App\Filament\Shared\Resources\AtcProfiles\AtcProfileResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;

class EditAtcProfile extends EditRecord
{
    protected static string $resource = AtcProfileResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    public static string|Alignment $formActionsAlignment = Alignment::End;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->label('Save ATC Profile');
    }
}
