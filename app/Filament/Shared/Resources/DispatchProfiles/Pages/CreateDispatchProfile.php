<?php

namespace App\Filament\Shared\Resources\DispatchProfiles\Pages;

use App\Filament\Shared\Resources\Concerns\AssignsUserOperatorFromProfileForm;
use App\Filament\Shared\Resources\DispatchProfiles\DispatchProfileResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;

class CreateDispatchProfile extends CreateRecord
{
    use AssignsUserOperatorFromProfileForm;

    protected static string $resource = DispatchProfileResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    public static bool $formActionsAreSticky = false;

    public static string|Alignment $formActionsAlignment = Alignment::End;

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Create Dispatch Profile');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->captureProfileFormOperator($data);
    }

    protected function afterCreate(): void
    {
        $this->saveProfileFormOperator();
    }
}
