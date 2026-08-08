<?php

namespace App\Filament\Shared\Resources\PilotProfiles\Pages;

use App\Filament\Shared\Resources\Concerns\AssignsUserOperatorFromProfileForm;
use App\Filament\Shared\Resources\PilotProfiles\PilotProfileResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;

class EditPilotProfile extends EditRecord
{
    use AssignsUserOperatorFromProfileForm;

    protected static string $resource = PilotProfileResource::class;

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
        return parent::getSaveFormAction()->label('Save Pilot Profile');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->captureProfileFormOperator($data);
    }

    protected function afterSave(): void
    {
        $this->saveProfileFormOperator();
    }
}
