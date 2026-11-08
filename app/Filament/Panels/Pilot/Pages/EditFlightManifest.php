<?php

namespace App\Filament\Panels\Pilot\Pages;

use App\Auth\AuthenticationEvidence;
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
        if (request()->boolean('confirm_signature')) {
            $this->mountAction('signManifest');
        } elseif (request()->boolean('confirm_flight_plan')) {
            $this->mountAction('signFlightPlan');
        }
    }

    public function signManifestAction(): Action
    {
        return $this->signatureAction('signManifest', 'passenger_cargo_manifest', 'Authorize Manifest Signature');
    }

    public function signFlightPlanAction(): Action
    {
        return $this->signatureAction('signFlightPlan', 'flight_plan', 'Authorize Flight Filing');
    }

    private function signatureAction(string $name, string $type, string $heading): Action
    {
        return Action::make($name)->label($heading)->requiresConfirmation()->modalHeading($heading)
            ->modalDescription($type === 'flight_plan'
                ? 'Sign the current saved flight plan only. The manifest requires its own signature confirmation.'
                : 'Sign the saved passenger/cargo manifest only. Unsaved edits are not included. Your existing flight-plan signature is preserved.')
            ->modalSubmitActionLabel('Authorize & Sign')->modalCancelActionLabel('Cancel')
            ->visible(function () use ($type): bool {
                $flight = Flight::findOrFail($this->flightId);

                return auth()->user()?->isPilot() && (int) $flight->pilot_in_command_user_id === (int) auth()->id()
                    && ($type !== 'flight_plan' || (int) $flight->prepared_by_user_id === (int) auth()->id())
                    && ! $flight->hasBeenAccepted() && ! $flight->isAvsecFrozen() && ! $flight->isPicAuthorizationDeclined()
                    && ($type !== 'passenger_cargo_manifest' || $flight->manifest()->exists());
            })
            ->mountUsing(function () use ($type): void {
                $flight = Flight::findOrFail($this->flightId);
                Gate::authorize('view', $flight);
                $documents = app(FilingDocumentRegistry::class)->snapshots($flight);
                abort_unless(isset($documents[$type]), 422);
                $this->reviewedFingerprint = FilingDocumentRegistry::documentFingerprint($documents, $type);
                $this->reviewedSignatureId = auth()->user()->approvedSignature()->where('status', 'approved')->value('id');
            })
            ->action(function () use ($type): void {
                try {
                    if ($this->reviewedSignatureId === null || $this->reviewedFingerprint === null) {
                        throw ValidationException::withMessages(['signature' => 'An approved pilot signature is required. Your manifest remains saved; reopen confirmation after signature approval.']);
                    }
                    app(PicAuthorizationService::class)->authorizeSelfFiling(Flight::findOrFail($this->flightId),
                        auth()->user(), AuthenticationEvidence::fromSession(Filament::auth()),
                        $this->reviewedSignatureId, $this->reviewedFingerprint, $type);
                    Notification::make()->success()->title('Saved document signed')->send();
                } catch (ValidationException $exception) {
                    Notification::make()->danger()->title('Document remains saved; signature not applied')
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
