<?php

namespace App\Filament\Shared\Resources\Flights\Pages;

use App\Domain\FlightPlans\Services\FlightPlanMutationService;
use App\Domain\FlightPlans\Support\AuthenticatedOperatorFlightData;
use App\Domain\FlightPlans\Support\FlightAccess;
use App\Domain\FlightPlans\Support\FlightPlanPreparerContext;
use App\Domain\FlightPlans\Support\PilotFlightPlanCredentials;
use App\Filament\Panels\Pilot\Resources\AwaitingAuthorizationFlights\AwaitingAuthorizationFlightResource;
use App\Filament\Panels\Pilot\Resources\MyCurrentFlights\MyCurrentFlightResource;
use App\Filament\Shared\Resources\Flights\FlightResource;
use App\Filament\Shared\Resources\Flights\Schemas\FlightForm;
use App\Models\Flight;
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

    public function mount(): void
    {
        parent::mount();

        $sourceId = request()->integer('correct_from');
        if ($sourceId <= 0) {
            return;
        }

        $source = Flight::query()->find($sourceId);
        abort_unless($source !== null
            && $source->pic_authorization_status === 'declined'
            && (int) $source->prepared_by_user_id === (int) auth()->id()
            && FlightAccess::canView(auth()->user(), $source), 403);

        $excluded = ['id', 'created_at', 'updated_at', 'status', 'revision_number', 'revision_of_id',
            'pic_authorized_by_user_id', 'pic_authorized_at', 'pic_authorization_method',
            'pic_authorization_token', 'pic_authorization_token_expires_at', 'pic_authorized_revision',
            'pic_authorization_status', 'pic_authorization_declined_by_user_id',
            'pic_authorization_declined_at', 'pic_authorization_decline_reason', 'accepted_by_user_id',
            'accepted_by_wiresign', 'rejected_by_wiresign', 'rejection_reason', 'reviewed_at'];
        $this->form->fill(collect($source->getAttributes())->except($excluded)->all());
        $this->data['revision_of_id'] = $source->getKey();
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

        $sourceId = (int) ($data['revision_of_id'] ?? request()->integer('correct_from'));
        $source = $sourceId > 0 ? Flight::query()->find($sourceId) : null;
        if ($source !== null) {
            abort_unless($source->pic_authorization_status === 'declined'
                && (int) $source->prepared_by_user_id === (int) auth()->id(), 403);
            $data['revision_of_id'] = $source->getKey();
            $data['revision_number'] = (int) ($source->revision_number ?? 1) + 1;
            $data['pic_authorization_status'] = 'pending';
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
