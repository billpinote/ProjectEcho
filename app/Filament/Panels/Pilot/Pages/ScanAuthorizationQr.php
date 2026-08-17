<?php

namespace App\Filament\Panels\Pilot\Pages;

use App\Domain\FlightPlans\Services\PicAuthorizationService;
use App\Domain\Users\Enums\UserRole;
use App\Filament\Shared\Pages\ImportScanQr;
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

    public function canAuthorizeMatchedFlight(): bool
    {
        $flight = $this->matchedFlight !== null ? Flight::find((int) $this->matchedFlight['id']) : null;

        if ($flight === null || ! $flight->requiresPicAuthorization() || $flight->isPicAuthorizationCurrent()) {
            return false;
        }

        try {
            app(PicAuthorizationService::class)->eligibleCredentials(auth()->user(), $flight);

            return (int) $flight->prepared_by_user_id !== (int) auth()->id();
        } catch (ValidationException) {
            return false;
        }
    }

    public function authorizeAsPic(): void
    {
        try {
            app(PicAuthorizationService::class)->authorizeFromPayload($this->payload, auth()->user());
            $this->lookupPayload($this->payload, false, false);
            Notification::make()->success()->title('Flight plan authorized as PIC')->send();
        } catch (ValidationException $exception) {
            $this->setValidationErrors($exception);
        }
    }

    public function declineAuthorization(): void
    {
        try {
            app(PicAuthorizationService::class)->declineFromPayload($this->payload, auth()->user(), $this->declineReason);
            $this->lookupPayload($this->payload, false, false);
            Notification::make()->success()->title('PIC authorization declined')->send();
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
