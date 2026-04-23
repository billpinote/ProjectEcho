<?php

namespace App\Filament\Pages;

use App\Enums\FlightPlanStatus;
use App\Models\Flight;
use BackedEnum;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Validator;

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
            'payload' => ['required', 'string', 'max:255'],
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
        $normalizedPayload = strtoupper(trim($value));

        if (! preg_match('/^ECHOFPL\|1\|DB\|\d+$/', $normalizedPayload)) {
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
        $normalizedPayload = strtoupper(trim($payload));
        $parts = explode('|', $normalizedPayload);

        if (count($parts) !== 4 || $parts[0] !== 'ECHOFPL' || $parts[1] !== '1' || $parts[2] !== 'DB' || ! ctype_digit($parts[3])) {
            $this->matchedFlight = null;
            $this->lastProcessedPayload = null;

            if ($notifyOnFailure) {
                Notification::make()
                    ->title('Invalid QR payload')
                    ->body('Expected format: ECHOFPL|1|DB|{flight_id}')
                    ->danger()
                    ->send();
            }

            return;
        }

        $flight = Flight::find((int) $parts[3]);

        if (! $flight) {
            $this->matchedFlight = null;
            $this->lastProcessedPayload = $normalizedPayload;

            if ($notifyOnFailure) {
                Notification::make()
                    ->title('Flight not found')
                    ->body('No saved flight plan matches that QR payload.')
                    ->danger()
                    ->send();
            }

            return;
        }

        $this->payload = $normalizedPayload;
        $this->lastProcessedPayload = $normalizedPayload;
        $status = $flight->status instanceof FlightPlanStatus ? $flight->status : FlightPlanStatus::tryFrom((string) $flight->status);

        $this->matchedFlight = [
            'id' => $flight->getKey(),
            'aircraft_identification' => (string) ($flight->aircraft_identification ?? 'N/A'),
            'date_of_flight' => $this->formatFlightDate($flight->date_of_flight),
            'proposed_time' => (string) ($flight->proposed_time ?? 'N/A'),
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
}
