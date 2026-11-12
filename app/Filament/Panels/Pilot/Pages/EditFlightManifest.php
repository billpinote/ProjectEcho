<?php

namespace App\Filament\Panels\Pilot\Pages;

use App\Auth\AuthenticationEvidence;
use App\Domain\FlightPlans\Services\FlightManifestService;
use App\Domain\FlightPlans\Services\PicAuthorizationService;
use App\Domain\FlightPlans\Support\FilingDocumentRegistry;
use App\Http\Controllers\FlightManifestController;
use App\Models\Flight;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

class EditFlightManifest extends Page
{
    protected static ?string $title = 'Flight Manifest';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'flights/{flight}/manifest';

    protected string $view = 'filament.pages.flight-manifest';

    #[Locked]
    public int $flightId;

    #[Locked]
    public ?string $reviewedFingerprint = null;

    #[Locked]
    public ?int $reviewedSignatureId = null;

    public function mount(Flight $flight): void
    {
        $this->flightId = $flight->id;
        Gate::authorize('view', $flight);
        if ((request()->boolean('confirm_signature') || request()->boolean('confirm_flight_plan'))
            && $this->authorizationUnavailableReason() === null) {
            $this->mountAction('signFiling');
        }
    }

    public function authorizationUnavailableReason(): ?string
    {
        $flight = Flight::findOrFail($this->flightId);
        if (! app(PicAuthorizationService::class)->needsAuthorization($flight)) {
            return 'The current filing package has already been authorized.';
        }

        return app(PicAuthorizationService::class)->authorizationUnavailableReason($flight, auth()->user());
    }

    public function signFilingAction(): Action
    {
        return Action::make('signFiling')->label('Authorize & Sign Filing')->requiresConfirmation()
            ->modalHeading('Authorize Complete Filing')
            ->modalDescription('Review the current flight plan and complete passenger/cargo manifest. One signature authorizes this entire filing package. Unsaved edits are not included.')
            ->modalSubmitActionLabel('Authorize & Sign Filing')->modalCancelActionLabel('Cancel')
            ->visible(function (): bool {
                $flight = Flight::findOrFail($this->flightId);

                return auth()->user()?->isPilot() && auth()->user()->is_active
                    && (int) $flight->pilot_in_command_user_id === (int) auth()->id()
                    && app(PicAuthorizationService::class)->canReviewFiling(auth()->user(), $flight);
            })
            ->disabled(fn (): bool => $this->authorizationUnavailableReason() !== null)
            ->mountUsing(function (): void {
                $flight = Flight::findOrFail($this->flightId);
                Gate::authorize('view', $flight);
                app(FlightManifestService::class)->assertComplete($flight);
                $this->reviewedFingerprint = FilingDocumentRegistry::fingerprint(app(FilingDocumentRegistry::class)->snapshots($flight));
                $this->reviewedSignatureId = auth()->user()->approvedSignature()->where('status', 'approved')->value('id');
            })
            ->action(function (): void {
                try {
                    if ($this->reviewedSignatureId === null || $this->reviewedFingerprint === null) {
                        throw ValidationException::withMessages(['signature' => 'An approved pilot signature and current package review are required. Your saved documents have been preserved.']);
                    }
                    app(PicAuthorizationService::class)->authorizeSelfFiling(Flight::findOrFail($this->flightId),
                        auth()->user(), AuthenticationEvidence::fromSession(Filament::auth()),
                        $this->reviewedSignatureId, $this->reviewedFingerprint);
                    Notification::make()->success()->title('Complete filing authorized and signed')->send();
                } catch (ValidationException $exception) {
                    Notification::make()->danger()->title('Filing remains saved; signature not applied')
                        ->body(collect($exception->errors())->flatten()->implode(' '))->send();
                    throw $exception;
                }
            });
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return app(FlightManifestController::class)->formData(request(), Flight::findOrFail($this->flightId));
    }
}
