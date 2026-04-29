<?php

namespace App\Filament\Pages;

use App\Enums\FlightPlanStatus;
use App\Models\Flight;
use App\Rules\UtcFourDigitTime;
use App\Services\FlightPlanQrPayloadService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ImportScanQr extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static ?string $navigationLabel = 'Import / Scan QR';

    protected static ?string $navigationParentItem = 'Flight Plan';

    protected static ?int $navigationSort = 20;

    protected string $view = 'filament.pages.import-scan-qr';

    public string $payload = '';

    public ?string $lastProcessedPayload = null;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $matchedFlight = null;

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'payload' => ['required', 'string', 'max:20000'],
        ];
    }

    public function submit(): void
    {
        $validated = Validator::make(
            ['payload' => trim($this->payload)],
            $this->rules(),
            [
                'payload.required' => 'Paste or scan a QR payload first.',
            ],
        )->validate();

        $this->lookupPayload($validated['payload'], notifyOnSuccess: true, notifyOnFailure: true);
    }

    public function updatedPayload(string $value): void
    {
        $normalizedPayload = trim($value);

        if (! $this->qrPayloads()->looksLikeSupportedPayload($normalizedPayload)) {
            return;
        }

        if ($this->lastProcessedPayload === $normalizedPayload) {
            return;
        }

        $this->lookupPayload($normalizedPayload, notifyOnSuccess: false, notifyOnFailure: false);
    }

    private function formatFlightDate(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'N/A';
        }

        if ($value instanceof Carbon) {
            return $value->format('Y-m-d');
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function lookupPayload(string $payload, bool $notifyOnSuccess, bool $notifyOnFailure): void
    {
        $parsedPayload = $this->qrPayloads()->parsePayload($payload);

        if ($parsedPayload === null) {
            $this->matchedFlight = null;
            $this->lastProcessedPayload = null;

            if ($notifyOnFailure) {
                Notification::make()
                    ->title('Invalid QR payload')
                    ->body('Expected a valid Echo QR payload. V2 signed offline payloads and legacy V1 database payloads are supported.')
                    ->danger()
                    ->send();
            }

            return;
        }

        if (($parsedPayload['format'] ?? null) === 'v2-offline') {
            $snapshot = $parsedPayload['snapshot'] ?? null;

            if (! is_array($snapshot)) {
                $this->matchedFlight = null;
                $this->lastProcessedPayload = null;

                return;
            }

            $flight = Flight::find((int) $parsedPayload['flight_id']);
            $status = $flight?->status instanceof FlightPlanStatus
                ? $flight->status
                : FlightPlanStatus::tryFrom((string) ($flight?->status ?? ''));
            $previewToken = $this->storeScannedFlightPlanPreview([
                'payload' => $parsedPayload['normalized_payload'],
                'snapshot' => $snapshot,
                'flight_id' => $parsedPayload['flight_id'],
                'issued_at' => $parsedPayload['issued_at'],
                'key_id' => $parsedPayload['key_id'],
                'schema_id' => $parsedPayload['schema_id'],
            ]);

            $this->payload = $parsedPayload['normalized_payload'];
            $this->lastProcessedPayload = $parsedPayload['normalized_payload'];
            $this->matchedFlight = [
                'id' => (int) $parsedPayload['flight_id'],
                'aircraft_identification' => (string) ($snapshot['aircraft_identification'] ?? 'N/A'),
                'date_of_flight' => $this->formatFlightDate($snapshot['date_of_flight'] ?? null),
                'proposed_time' => UtcFourDigitTime::formatForDisplay($snapshot['proposed_time'] ?? null) ?? 'N/A',
                'departure_aerodrome' => (string) ($snapshot['departure_aerodrome'] ?? 'N/A'),
                'destination_aerodrome' => (string) ($snapshot['destination_aerodrome'] ?? 'N/A'),
                'status' => $status?->value ?? 'signed_offline_payload',
                'status_label' => $status?->label() ?? 'Signed Offline Payload',
                'status_color' => $status?->filamentColor() ?? 'info',
                'view_url' => $flight
                    ? route('flights.view', $flight)
                    : route('flightplan.scan-qr.preview', ['token' => $previewToken]),
            ];

            if ($notifyOnSuccess) {
                Notification::make()
                    ->title('Signed flight plan loaded')
                    ->success()
                    ->send();
            }

            return;
        }

        $flight = Flight::find((int) $parsedPayload['flight_id']);

        if (! $flight) {
            $this->matchedFlight = null;
            $this->lastProcessedPayload = $parsedPayload['normalized_payload'];

            if ($notifyOnFailure) {
                Notification::make()
                    ->title('Flight not found')
                    ->body('No saved flight plan matches that QR payload.')
                    ->danger()
                    ->send();
            }

            return;
        }

        $this->payload = $parsedPayload['normalized_payload'];
        $this->lastProcessedPayload = $parsedPayload['normalized_payload'];
        $status = $flight->status instanceof FlightPlanStatus ? $flight->status : FlightPlanStatus::tryFrom((string) $flight->status);

        $this->matchedFlight = [
            'id' => $flight->getKey(),
            'aircraft_identification' => (string) ($flight->aircraft_identification ?? 'N/A'),
            'date_of_flight' => $this->formatFlightDate($flight->date_of_flight),
            'proposed_time' => UtcFourDigitTime::formatForDisplay($flight->proposed_time) ?? 'N/A',
            'departure_aerodrome' => (string) ($flight->departure_aerodrome ?? 'N/A'),
            'destination_aerodrome' => (string) ($flight->destination_aerodrome ?? 'N/A'),
            'status' => $status?->value ?? (string) ($flight->status ?? 'unknown'),
            'status_label' => $status?->label() ?? str((string) ($flight->status ?? 'unknown'))->headline()->toString(),
            'status_color' => $status?->filamentColor() ?? 'gray',
            'view_url' => route('flights.view', $flight),
        ];

        if ($notifyOnSuccess) {
            Notification::make()
                ->title('Flight plan loaded')
                ->success()
                ->send();
        }
    }

    /**
     * @param  array<string, mixed>  $preview
     */
    private function storeScannedFlightPlanPreview(array $preview): string
    {
        $previewToken = (string) Str::uuid();
        $previews = session()->get('scanned_flight_plan_previews', []);

        if (! is_array($previews)) {
            $previews = [];
        }

        $previews[$previewToken] = $preview;

        if (count($previews) > 10) {
            $previews = array_slice($previews, -10, null, true);
        }

        session()->put('scanned_flight_plan_previews', $previews);

        return $previewToken;
    }

    private function qrPayloads(): FlightPlanQrPayloadService
    {
        return app(FlightPlanQrPayloadService::class);
    }
}
