<?php

namespace App\Filament\Panels\Avsec\Pages;

use App\Domain\FlightPlans\Rules\UtcFourDigitTime;
use App\Domain\FlightPlans\Services\AvsecVerificationService;
use App\Domain\FlightPlans\Services\FilingAuthorizationService;
use App\Filament\Shared\Pages\ImportScanQr as SharedImportScanQr;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class ImportScanQr extends SharedImportScanQr
{
    public bool $clearanceConfirmed = false;

    public ?string $pnpReference = null;

    public static function canAccess(): bool
    {
        return AvsecVerificationService::eligibleOfficer(Auth::user());
    }

    public function isAvsecVerificationPage(): bool
    {
        return true;
    }

    protected function lookupPayload(string $payload, bool $notifyOnSuccess, bool $notifyOnFailure): void
    {
        $this->resetErrorBag();
        $this->matchedFlight = null;
        $this->clearanceConfirmed = false;
        $this->pnpReference = null;
        $flight = app(AvsecVerificationService::class)->resolve($payload, Auth::user());
        $verification = $flight->avsecVerification()->first();
        $this->lastProcessedPayload = $payload;
        $this->payload = $payload;
        $this->matchedFlight = [
            'id' => $flight->id, 'revision' => $flight->revision_number,
            'aircraft_identification' => $flight->aircraft_identification,
            'aircraft' => $flight->type_of_aircraft, 'pic' => $flight->pilot_in_command,
            'date_of_flight' => $flight->date_of_flight,
            'proposed_time' => UtcFourDigitTime::formatForDisplay($flight->currentEobt()),
            'departure_aerodrome' => $flight->departure_aerodrome, 'destination_aerodrome' => $flight->destination_aerodrome,
            'status_color' => $verification ? 'success' : 'warning',
            'status_label' => $verification ? 'AVSEC confirmed — submission frozen' : 'Awaiting AVSEC confirmation',
            'authorized' => app(FilingAuthorizationService::class)->isReadyForAtmo($flight),
            'verified' => $verification !== null,
            'view_url' => route('flights.view', $flight),
            'manifest_url' => $flight->manifest()->exists() ? route('flights.manifest.pdf', $flight) : null,
        ];
    }

    public function confirmClearance(): void
    {
        app(AvsecVerificationService::class)->confirm($this->payload, Auth::user(), $this->clearanceConfirmed, $this->pnpReference);
        $this->lookupPayload($this->payload, false, false);
        Notification::make()->title('AVSEC confirmation recorded. Submission permanently frozen.')->success()->send();
    }
}
