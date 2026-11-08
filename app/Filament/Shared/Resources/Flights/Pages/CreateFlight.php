<?php

namespace App\Filament\Shared\Resources\Flights\Pages;

use App\Auth\AuthenticationEvidence;
use App\Domain\FlightPlans\Services\FlightPlanMutationService;
use App\Domain\FlightPlans\Services\FlightPlanSubmissionService;
use App\Domain\FlightPlans\Services\PicAuthorizationService;
use App\Domain\FlightPlans\Support\AuthenticatedOperatorFlightData;
use App\Domain\FlightPlans\Support\FilingDocumentRegistry;
use App\Domain\FlightPlans\Support\FlightAccess;
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
    public ?int $reviewedSignatureId = null;

    #[Locked]
    public bool $fromSessionPreview = false;

    /** @var list<string> */
    #[Locked]
    public array $reviewedDocumentTypes = [];

    private bool $confirmedSelfFiling = false;

    #[Locked]
    public ?int $correctionSourceId = null;

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
        $this->correctionSourceId = (int) $source->getKey();
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
            ->modalHeading('Authorize Flight Filing')
            ->modalDescription(fn (): string => 'You are about to authorize and submit this flight plan and its attached documents using your verified pilot signature. Documents included: '.collect($this->reviewedDocumentTypes)->map(fn (string $type): string => str($type)->replace('_', ' ')->ucfirst()->toString())->implode(', ').'.')
            ->modalSubmitActionLabel('Authorize & Submit')
            ->modalCancelActionLabel('Cancel')
            ->mountUsing(function (): void {
                $this->authorizeAccess();
                $data = $this->mutateFormDataBeforeCreate($this->form->getState());
                try {
                    $signature = app(PicAuthorizationService::class)->selfFilingSignature(auth()->user()->fresh());
                } catch (ValidationException $exception) {
                    $this->reportAuthorizationFailure($exception);
                }
                $this->reviewedSignatureId = $signature->id;
                $this->reviewedFormFingerprint = FilingDocumentRegistry::fingerprint($data);
                $this->reviewedDocumentTypes = array_keys(app(FilingDocumentRegistry::class)->snapshots(new Flight($data)));
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

        $sourceId = $this->correctionSourceId ?? 0;
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
        if ($this->isSelfFiling()) {
            if (! $this->confirmedSelfFiling || $this->reviewedSignatureId === null
                || $this->reviewedFormFingerprint === null
                || ! hash_equals($this->reviewedFormFingerprint, FilingDocumentRegistry::fingerprint($data))) {
                $this->reportAuthorizationFailure(ValidationException::withMessages([
                    'signature' => 'The filing information changed. Review and confirm the filing again.',
                ]));
            }
            try {
                return app(FlightPlanSubmissionService::class)->submitSelfAuthorized($data, auth()->user(),
                    AuthenticationEvidence::fromSession(Filament::auth()), $this->reviewedSignatureId, $this->selfSubmissionId, $this->reviewedDocumentTypes);
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

        return parent::getRedirectUrl();
    }
}
