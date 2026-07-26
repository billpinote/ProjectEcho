<?php

namespace App\Filament\Resources\DispatchProfiles\Pages;

use App\Filament\Resources\DispatchProfiles\DispatchProfileResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;

class EditDispatchProfile extends EditRecord
{
    protected static string $resource = DispatchProfileResource::class;

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
        return parent::getSaveFormAction()->label('Save Dispatch Profile');
    }
}
