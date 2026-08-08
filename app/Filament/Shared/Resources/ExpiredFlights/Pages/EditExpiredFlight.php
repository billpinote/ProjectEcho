<?php

namespace App\Filament\Shared\Resources\ExpiredFlights\Pages;

use App\Filament\Shared\Resources\ExpiredFlights\ExpiredFlightResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;

class EditExpiredFlight extends EditRecord
{
    protected static string $resource = ExpiredFlightResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    public static string|Alignment $formActionsAlignment = Alignment::End;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => auth()->user()?->canDeleteFlightPlans() ?? false),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return ExpiredFlightResource::normalizeFormData($data);
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->label('Save Flight Plan');
    }
}
