<?php

namespace App\Filament\Resources\Flights\Pages;

use App\Filament\Resources\Flights\FlightResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;

class CreateFlight extends CreateRecord
{
    protected static string $resource = FlightResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    protected static bool $canCreateAnother = false;

    public static bool $formActionsAreSticky = false;

    public static string|Alignment $formActionsAlignment = Alignment::End;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return FlightResource::normalizeFormData($data);
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->label('Create Flight Plan');
    }
}
