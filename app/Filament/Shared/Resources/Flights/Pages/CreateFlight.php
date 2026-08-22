<?php

namespace App\Filament\Shared\Resources\Flights\Pages;

use App\Domain\FlightPlans\Services\FlightPlanMutationService;
use App\Domain\FlightPlans\Support\AuthenticatedOperatorFlightData;
use App\Domain\FlightPlans\Support\FlightPlanPreparerContext;
use App\Domain\FlightPlans\Support\PilotFlightPlanCredentials;
use App\Filament\Panels\Pilot\Resources\AwaitingAuthorizationFlights\AwaitingAuthorizationFlightResource;
use App\Filament\Panels\Pilot\Resources\MyCurrentFlights\MyCurrentFlightResource;
use App\Filament\Shared\Resources\Flights\FlightResource;
use App\Filament\Shared\Resources\Flights\Schemas\FlightForm;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateFlight extends CreateRecord
{
    protected static string $resource = FlightResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    protected static bool $canCreateAnother = false;

    public static bool $formActionsAreSticky = false;

    public static string|Alignment $formActionsAlignment = Alignment::End;

    public function getTitle(): string
    {
        return 'Create New Flight Plan';
    }

    public static function authorizeResourceAccess(): void
    {
        abort_unless(static::getResource()::canCreate(), 403);
    }

    public static function canAccess(array $parameters = []): bool
    {
        return static::getResource()::canCreate();
    }

    public function togglePilotPicCapacity(): void
    {
        $this->data = FlightForm::togglePilotPicCapacityState($this->data);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();
        $preparerContext = FlightPlanPreparerContext::for($user, $data);
        $data = $preparerContext->applyToFlightData($data);

        $data = AuthenticatedOperatorFlightData::apply($data, $user);

        if ($user?->isPilot() && $preparerContext->preparerActsAsPic()) {
            $messages = PilotFlightPlanCredentials::validationMessages($user, $data['date_of_flight'] ?? null);

            if ($messages !== []) {
                throw ValidationException::withMessages(
                    collect($messages)
                        ->mapWithKeys(fn (string $message, string $field): array => ["data.{$field}" => $message])
                        ->all()
                );
            }

            $data = PilotFlightPlanCredentials::applySnapshot($data, $user);
        }

        return FlightResource::normalizeFormData($data);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $flight = static::getModel()::create($data);

        app(FlightPlanMutationService::class)->recordSubmission($flight, auth()->user());

        return $flight;
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->label('Create Flight Plan');
    }

    protected function getRedirectUrl(): string
    {
        if (auth()->user()?->isPilot()) {
            if ($this->getRecord()?->requiresPicAuthorization()) {
                return AwaitingAuthorizationFlightResource::getUrl('index', panel: 'pilot');
            }

            return MyCurrentFlightResource::getUrl('index', panel: 'pilot');
        }

        return parent::getRedirectUrl();
    }
}
