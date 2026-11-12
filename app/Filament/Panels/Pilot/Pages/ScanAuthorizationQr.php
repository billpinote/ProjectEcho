<?php

namespace App\Filament\Panels\Pilot\Pages;

use App\Auth\AuthenticationEvidence;
use App\Domain\FlightPlans\Rules\UtcFourDigitTime;
use App\Domain\FlightPlans\Services\FilingAuthorizationService;
use App\Domain\FlightPlans\Services\PicAuthorizationService;
use App\Domain\FlightPlans\Support\FlightAccess;
use App\Domain\FlightPlans\Support\FlightStatusDisplay;
use App\Domain\Users\Enums\UserRole;
use App\Filament\Panels\Pilot\Resources\MyArchivedFlights\MyArchivedFlightResource;
use App\Filament\Shared\Pages\ImportScanQr;
use App\Models\Flight;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

class ScanAuthorizationQr extends ImportScanQr
{
    public string $declineReason = '';

    protected static ?string $navigationLabel = 'Scan Authorization QR';

    protected static string|\UnitEnum|null $navigationGroup = 'PIC Authorization';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->is_active
            && in_array($user->role, [UserRole::Pilot, UserRole::Artisan], true);
    }

    public function isPicAuthorizationPage(): bool
    {
        return true;
    }

    protected function canAccessScannedFlight(Flight $flight): bool
    {
        $user = auth()->user();

        return $user !== null && FlightAccess::canAccessPicAuthorization($user, $flight);
    }

    protected function scannedPreviewPurpose(): ?string
    {
        return 'pic_authorization';
    }

    protected function scannedFlightViewUrl(?Flight $flight, string $previewToken): string
    {
        if ($flight !== null && ! app(PicAuthorizationService::class)->needsAuthorization($flight) && ! $flight->isPicAuthorizationDeclined()) {
            return route('flights.view', $flight);
        }

        if ($flight?->isPicAuthorizationDeclined()) {
            return route('flightplan.scan-qr.preview', ['token' => $previewToken]);
        }

        return route('flightplan.pic-authorization.preview', ['token' => $previewToken]);
    }

    public function canAuthorizeMatchedFlight(): bool
    {
        $flight = $this->matchedFlight !== null ? Flight::find((int) $this->matchedFlight['id']) : null;

        if ($flight === null
            || $flight->isPicAuthorizationDeclined()
            || ! app(PicAuthorizationService::class)->canReviewFiling(auth()->user(), $flight)) {
            return false;
        }

        try {
            app(PicAuthorizationService::class)->eligibleCredentials(auth()->user(), $flight);

            return (int) $flight->prepared_by_user_id !== (int) auth()->id()
                || (int) $flight->pilot_in_command_user_id === (int) auth()->id();
        } catch (ValidationException) {
            return false;
        }
    }

    public function isPicAuthorizationPreparer(): bool
    {
        return $this->matchedFlight !== null
            && (int) $this->matchedFlight['id'] > 0
            && (int) Flight::query()->whereKey($this->matchedFlight['id'])->value('prepared_by_user_id') === (int) auth()->id()
            && (int) Flight::query()->whereKey($this->matchedFlight['id'])->value('pilot_in_command_user_id') !== (int) auth()->id();
    }

    public function isPicAuthorizationDeclined(): bool
    {
        return $this->matchedFlight !== null
            && Flight::query()->whereKey($this->matchedFlight['id'])->value('pic_authorization_status') === 'declined';
    }

    public function isPicAuthorizationDeclineActor(): bool
    {
        return $this->matchedFlight !== null
            && (int) Flight::query()->whereKey($this->matchedFlight['id'])->value('pic_authorization_declined_by_user_id') === (int) auth()->id();
    }

    public function authorizeAsPic(): void
    {
        $this->authorizeFiling(true);
    }

    public function authorizeAndSignAsPic(): void
    {
        $this->authorizeFiling(true);
    }

    public function filingDocuments(): array
    {
        $flight = app(PicAuthorizationService::class)->resolveAuthorizationHandoff($this->picAuthorizationHandoffToken ?? '');
        if ($flight === null || ! FlightAccess::canAccessPicAuthorization(auth()->user(), $flight)) {
            return [];
        }
        $package = app(FilingAuthorizationService::class)->latestFor($flight);

        return collect($package?->documents ?? [])->map(fn (array $document, string $type): array => [
            'label' => str($type)->headline()->toString(),
            'url' => route('filing.document', ['package' => $package, 'type' => $type, 'token' => $this->picAuthorizationHandoffToken]),
        ])->all();
    }

    public function authorizationUnavailableReason(): ?string
    {
        $flight = $this->matchedFlight !== null ? Flight::find((int) $this->matchedFlight['id']) : null;

        return $flight === null ? 'Load a current filing first.'
            : app(PicAuthorizationService::class)->authorizationUnavailableReason($flight, auth()->user());
    }

    public function hasApprovedSignature(): bool
    {
        return auth()->user()?->approvedSignature()->where('status', 'approved')->exists() ?? false;
    }

    private function authorizeFiling(bool $applySignature): void
    {
        try {
            $flight = app(PicAuthorizationService::class)->resolveAuthorizationHandoff($this->picAuthorizationHandoffToken ?? '');
            $selfPrepared = $flight !== null && (int) $flight->prepared_by_user_id === (int) auth()->id()
                && (int) $flight->pilot_in_command_user_id === (int) auth()->id();
            $flight = app(PicAuthorizationService::class)->authorizeFromHandoff(
                $this->picAuthorizationHandoffToken ?? '',
                auth()->user(),
                AuthenticationEvidence::fromSession(Filament::auth()),
                applySignature: true,
                selfPrepared: $selfPrepared,
            );
            $this->picAuthorizationHandoffToken = null;
            $badge = FlightStatusDisplay::badge($flight, auth()->user()->role);
            $this->matchedFlight = [
                'id' => $flight->id, 'aircraft_identification' => $flight->aircraft_identification,
                'date_of_flight' => (string) $flight->date_of_flight,
                'proposed_time' => UtcFourDigitTime::formatForDisplay($flight->proposed_time),
                'departure_aerodrome' => $flight->departure_aerodrome, 'destination_aerodrome' => $flight->destination_aerodrome,
                'status' => $badge['key'], 'status_label' => $badge['label'], 'status_color' => $badge['color'],
                'view_url' => route('flights.view', $flight),
            ];
            Notification::make()->success()->title('Flight plan authorized as PIC')->send();
        } catch (ValidationException $exception) {
            $this->setValidationErrors($exception);
        }
    }

    public function declineAuthorization(?string $reason = null): void
    {
        if ($reason !== null) {
            $this->declineReason = $reason;
        }

        $this->resetErrorBag();
        if (blank(trim($this->declineReason)) || mb_strlen(trim($this->declineReason)) > 500) {
            $this->addError('declineReason', 'A reason for declining is required and must not exceed 500 characters.');

            return;
        }

        try {
            app(PicAuthorizationService::class)->declineFromHandoff(
                $this->picAuthorizationHandoffToken ?? '',
                auth()->user(),
                $this->declineReason,
                AuthenticationEvidence::fromSession(Filament::auth()),
            );
            $this->picAuthorizationHandoffToken = null;
            Notification::make()->success()->title('Flight plan declined and moved to your archive.')->send();
            $this->redirect(MyArchivedFlightResource::getUrl('index', panel: 'pilot'));
        } catch (ValidationException $exception) {
            $this->setValidationErrors($exception);
        }
    }

    private function setValidationErrors(ValidationException $exception): void
    {
        foreach ($exception->errors() as $field => $messages) {
            foreach ($messages as $message) {
                $this->addError($field, $message);
            }
        }
    }
}
