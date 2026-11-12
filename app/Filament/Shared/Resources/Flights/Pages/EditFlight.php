<?php

namespace App\Filament\Shared\Resources\Flights\Pages;

use App\Domain\FlightPlans\Support\AuthenticatedOperatorFlightData;
use App\Domain\FlightPlans\Support\FilingDocumentRegistry;
use App\Filament\Shared\Resources\Flights\FlightResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

class EditFlight extends EditRecord
{
    protected static string $resource = FlightResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    public static string|Alignment $formActionsAlignment = Alignment::End;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        abort_unless($this->record->pic_authorization_status !== 'declined', 403, 'PIC-declined flight-plan versions are read-only.');

        if (auth()->user()?->can('accept', $this->record) && $this->record->status?->value === 'pending' && ! $this->record->isPendingExpired()) {
            $this->record->markAsReviewed();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => auth()->user()?->canDeleteFlightPlans() ?? false),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $flight = $this->record->fresh();
        Gate::authorize('update', $flight);
        $actor = auth()->user();
        if ($actor->isPilot() || $actor->isDispatch()) {
            $data = Arr::except($data, [
                ...FilingDocumentRegistry::AUTHORIZATION_FIELDS,
                ...FilingDocumentRegistry::OPERATIONAL_FIELDS,
                'user_id', 'filed_by_user_id', 'prepared_by_user_id', 'prepared_by_name', 'prepared_by_role',
                'pilot_id', 'pilot_in_command_user_id', 'pilot_in_command', 'pilot_license_no', 'pilot_ratings',
                'license_expiry_date', 'filed_by_name', 'filed_by_signature', 'filing_capacity',
                'authorized_representative_enabled', 'authorized_representative_name', 'authorized_representative_role',
                'authorized_representative_id_license', 'authorized_representative_expiry_date',
            ]);
            $data = AuthenticatedOperatorFlightData::apply($data, $actor);
            // Normalization must not clear the trusted representative snapshot.
            $data = array_replace($data, $flight->only(['authorized_representative_enabled', 'authorized_representative_name',
                'authorized_representative_role', 'authorized_representative_id_license', 'authorized_representative_expiry_date']));
        }

        return FlightResource::normalizeFormData($data);
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->label('Save Flight Plan');
    }
}
