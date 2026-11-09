<?php

namespace App\Filament\Panels\Avsec\Pages;

use App\Domain\FlightPlans\Rules\UtcFourDigitTime;
use App\Domain\FlightPlans\Services\AvsecDepartureQuery;
use App\Domain\FlightPlans\Services\AvsecVerificationService;
use App\Domain\FlightPlans\Services\FilingAuthorizationService;
use App\Domain\FlightPlans\Services\FlightPlanQrPayloadService;
use App\Domain\FlightPlans\Support\FlightStatusDisplay;
use App\Filament\Panels\Avsec\Resources\FiledRpusDepartures\FiledRpusDepartureResource;
use App\Filament\Shared\Pages\ImportScanQr as SharedImportScanQr;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;

class ImportScanQr extends SharedImportScanQr
{
    protected static ?string $navigationLabel = 'Scan QR';

    protected static string|\UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = 3;

    public function getHeading(): ?string
    {
        return null;
    }

    protected static string $layout = 'filament-panels::components.layout.index';

    #[Locked]
    public ?string $reviewedFingerprint = null;

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        if (request()->has('flight')) {
            $flight = app(AvsecDepartureQuery::class)
                ->forOfficer(Auth::user())->findOrFail(request()->integer('flight'));
            abort_unless(Auth::user()->can('view', $flight), 403);
            $this->lookupPayload(app(FlightPlanQrPayloadService::class)->buildPayload($flight), false, false);
        }
    }

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
        $this->reviewedFingerprint = null;
        $flight = app(AvsecVerificationService::class)->resolve($payload, Auth::user());
        $this->reviewedFingerprint = app(AvsecVerificationService::class)->reviewFingerprint($flight);
        $verification = $flight->avsecVerification()->first();
        $status = FlightStatusDisplay::avsecBadge($flight, $verification !== null);
        $this->lastProcessedPayload = $payload;
        $this->payload = $payload;
        $flight->loadMissing('manifest.persons');
        $authorizations = app(FilingAuthorizationService::class);
        $this->matchedFlight = [
            'id' => $flight->id, 'revision' => $flight->revision_number,
            'aircraft_identification' => $flight->aircraft_identification,
            'aircraft' => $flight->type_of_aircraft, 'pic' => $flight->pilot_in_command,
            'date_of_flight' => $flight->date_of_flight,
            'proposed_time' => UtcFourDigitTime::formatForDisplay($flight->currentEobt()),
            'departure_aerodrome' => $flight->departure_aerodrome, 'destination_aerodrome' => $flight->destination_aerodrome,
            'status_color' => $status['color'],
            'status_label' => $status['label'],
            'authorized' => app(FilingAuthorizationService::class)->isReadyForAtmo($flight),
            'local_departure' => FiledRpusDepartureResource::departureTime($flight),
            'flight_authorized' => $authorizations->currentForDocument($flight, 'flight_plan') !== null,
            'manifest_authorized' => $authorizations->currentForDocument($flight, 'passenger_cargo_manifest') !== null,
            'persons' => $flight->manifest?->persons->map->only(['full_name', 'nationality', 'classification'])->all() ?? [],
            'cargo' => $flight->manifest?->only(['cargo_type', 'cargo_quantity', 'total_cargo_weight']),
            'verifier' => $verification?->verifier_name,
            'verified_at' => $verification?->verified_at?->setTimezone('Asia/Manila')->format('Y-m-d H:i'),
            'verified' => $verification !== null,
            'view_url' => route('flights.view', $flight),
            'manifest_url' => $flight->manifest()->exists() ? route('flights.manifest.pdf', $flight) : null,
        ];
    }

    public function confirmClearance(): void
    {
        abort_unless($this->reviewedFingerprint !== null, 403);
        app(AvsecVerificationService::class)->confirm($this->payload, Auth::user(), $this->clearanceConfirmed, $this->pnpReference, $this->reviewedFingerprint);
        $this->lookupPayload($this->payload, false, false);
        Notification::make()->title('AVSEC clearance recorded. Verified documents are permanently protected from changes.')->success()->send();
    }
}
