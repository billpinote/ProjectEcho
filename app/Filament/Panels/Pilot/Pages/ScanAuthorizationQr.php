<?php

namespace App\Filament\Panels\Pilot\Pages;

use App\Domain\FlightPlans\Services\PicAuthorizationService;
use App\Domain\FlightPlans\Support\FlightAccess;
use App\Domain\Users\Enums\UserRole;
use App\Filament\Shared\Pages\ImportScanQr;
use App\Filament\Panels\Pilot\Resources\MyArchivedFlights\MyArchivedFlightResource;
use App\Models\Flight;
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
            || ! $flight->requiresPicAuthorization()
            || $flight->isPicAuthorizationCurrent()) {
            return false;
        }

        try {
            app(PicAuthorizationService::class)->eligibleCredentials(auth()->user(), $flight);

            return (int) $flight->prepared_by_user_id !== (int) auth()->id();
        } catch (ValidationException) {
            return false;
        }
    }

    public function isPicAuthorizationPreparer(): bool
    {
        return $this->matchedFlight !== null
            && (int) $this->matchedFlight['id'] > 0
            && (int) Flight::query()->whereKey($this->matchedFlight['id'])->value('prepared_by_user_id') === (int) auth()->id();
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
        try {
            app(PicAuthorizationService::class)->authorizeFromHandoff($this->picAuthorizationHandoffToken ?? '', auth()->user());
            $this->picAuthorizationHandoffToken = null;
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
            app(PicAuthorizationService::class)->declineFromHandoff($this->picAuthorizationHandoffToken ?? '', auth()->user(), $this->declineReason);
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
