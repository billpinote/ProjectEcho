<?php

namespace App\Filament\Shared\Resources\Flights\Pages;

use App\Auth\AuthenticationEvidence;
use App\Domain\FlightPlans\Services\FlightPlanMutationService;
use App\Domain\FlightPlans\Services\FlightPlanSubmissionService;
use App\Domain\FlightPlans\Support\AuthenticatedOperatorFlightData;
use App\Domain\FlightPlans\Support\FilingDocumentRegistry;
use App\Domain\FlightPlans\Support\FlightPlanDataNormalizer;
use App\Domain\FlightPlans\Support\FlightPlanPreparerContext;
use App\Domain\FlightPlans\Support\PilotFlightPlanCredentials;
use App\Filament\Panels\Pilot\Resources\AwaitingAuthorizationFlights\AwaitingAuthorizationFlightResource;
use App\Filament\Panels\Pilot\Resources\MyCurrentFlights\MyCurrentFlightResource;
use App\Filament\Shared\Resources\Flights\FlightResource;
use App\Filament\Shared\Resources\Flights\Schemas\FlightForm;
use App\Models\Flight;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

class CreateFlight extends CreateRecord
{
    protected ?bool $hasDatabaseTransactions = true;

    #[Locked]
    public string $selfSubmissionId;

    #[Locked]
    public ?string $reviewedFormFingerprint = null;

    #[Locked]
    public bool $fromSessionPreview = false;

    private bool $confirmedSelfFiling = false;

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
        $this->selfSubmissionId = (string) Str::uuid();

        if (request()->boolean('from_preview') && is_array($preview = session('flight_plan_preview'))) {
            $this->form->fill(app(FlightPlanDataNormalizer::class)->prepareFlightPlanPreviewInput($preview));
            $this->fromSessionPreview = true;
        }

        abort_if(request()->has('correct_from'), 403, 'Submitted flight plans cannot be corrected and resubmitted.');
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

    public function create(bool $another = false): void
    {
        if ($this->isCreating) {
            return;
        }
        if ($this->isSelfFiling()) {
            $this->mountAction('authorizeFiling');

            return;
        }
        parent::create($another);
        $this->clearSubmittedSessionPreview();
    }

    public function authorizeFilingAction(): Action
    {
        return Action::make('authorizeFiling')
            ->visible(fn (): bool => $this->isSelfFiling())
            ->requiresConfirmation()
            ->modalHeading('Create Flight Plan')
            ->modalDescription('Save the flight plan, complete its manifest, then review and authorize the complete filing package. No PIC signature is applied at this step.')
            ->modalSubmitActionLabel('Create Flight Plan')
            ->modalCancelActionLabel('Cancel')
            ->mountUsing(function (): void {
                $this->authorizeAccess();
                $data = $this->mutateFormDataBeforeCreate($this->form->getState());
                $this->reviewedFormFingerprint = FilingDocumentRegistry::fingerprint($data);
            })
            ->action(function (): void {
                if (! $this->isSelfFiling()) {
                    $this->reportAuthorizationFailure(ValidationException::withMessages(['signature' => 'The filing capacity changed. Review the form and submit it again.']));
                }
                $this->confirmedSelfFiling = true;
                try {
                    parent::create();
                    $this->clearSubmittedSessionPreview();
                } finally {
                    $this->confirmedSelfFiling = false;
                }
            });
    }

    private function isSelfFiling(): bool
    {
        return auth()->user()?->isPilot()
            && FlightPlanPreparerContext::for(auth()->user(), $this->data ?? [])->preparerActsAsPic();
    }

    private function clearSubmittedSessionPreview(): void
    {
        if ($this->fromSessionPreview && $this->getRecord()?->exists) {
            session()->forget('flight_plan_preview');
        }
    }

    private function reportAuthorizationFailure(ValidationException $exception): never
    {
        $message = collect($exception->errors())->flatten()->first();
        Notification::make()->danger()->title('Unable to authorize filing')->body($message)->send();

        throw ValidationException::withMessages(['data.signature' => $message]);
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

        unset($data['revision_of_id'], $data['revision_number']);

        return FlightResource::normalizeFormData($data);
    }

    protected function handleRecordCreation(array $data): Model
    {
        if ($this->isSelfFiling()) {
            if (! $this->confirmedSelfFiling
                || $this->reviewedFormFingerprint === null
                || ! hash_equals($this->reviewedFormFingerprint, FilingDocumentRegistry::fingerprint($data))) {
                $this->reportAuthorizationFailure(ValidationException::withMessages([
                    'signature' => 'The filing information changed. Review and confirm the filing again.',
                ]));
            }
            try {
                return app(FlightPlanSubmissionService::class)->submitSelfPrepared($data, auth()->user(),
                    AuthenticationEvidence::fromSession(Filament::auth()), $this->selfSubmissionId);
            } catch (ValidationException $exception) {
                $this->reportAuthorizationFailure($exception);
            }
        }

        return DB::transaction(function () use ($data): Flight {
            $flight = static::getModel()::create($data)->refresh();

            app(FlightPlanMutationService::class)->recordSubmission($flight, auth()->user());

            return $flight;
        });
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

        if (auth()->user()?->isDispatch() || auth()->user()?->isOperatorStaff()) {
            return \App\Filament\Panels\Dispatch\Resources\AwaitingAuthorizationFlights\AwaitingAuthorizationFlightResource::getUrl('index', panel: 'dispatch');
        }

        return parent::getRedirectUrl();
    }
}
