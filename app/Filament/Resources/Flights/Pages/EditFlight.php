<?php

namespace App\Filament\Resources\Flights\Pages;

use App\Enums\FlightPlanStatus;
use App\Filament\Resources\Flights\FlightResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;

class EditFlight extends EditRecord
{
    protected static string $resource = FlightResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    public static string|Alignment $formActionsAlignment = Alignment::End;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if ($this->record->status === FlightPlanStatus::Pending && ! $this->record->isPendingExpired()) {
            $this->record->markAsReviewed();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return FlightResource::normalizeFormData($data);
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()
            ->label('Save Flight Plan');
    }
}
