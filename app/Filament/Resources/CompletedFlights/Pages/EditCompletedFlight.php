<?php

namespace App\Filament\Resources\CompletedFlights\Pages;

use App\Filament\Resources\CompletedFlights\CompletedFlightResource;
use App\Filament\Resources\Flights\Pages\Concerns\CreatesFlightRevisionForPilots;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;

class EditCompletedFlight extends EditRecord
{
    use CreatesFlightRevisionForPilots;

    protected static string $resource = CompletedFlightResource::class;

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
        return CompletedFlightResource::normalizeFormData($data);
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()
            ->label(auth()->user()?->createsFlightPlanRevisionsOnly() ? 'Create New Flight Plan' : 'Save Flight');
    }
}
