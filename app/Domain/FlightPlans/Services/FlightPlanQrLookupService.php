<?php

namespace App\Domain\FlightPlans\Services;

use App\Domain\FlightPlans\Enums\FlightPlanStatus;
use App\Domain\FlightPlans\Rules\UtcFourDigitTime;
use App\Models\Flight;
use Carbon\Carbon;

class FlightPlanQrLookupService
{
    public function __construct(
        private FlightPlanQrPayloadService $payloads,
    ) {}

    /** @return array<string, mixed>|null */
    public function lookup(string $payload): ?array
    {
        $parsedPayload = $this->payloads->parsePayload($payload);

        if ($parsedPayload === null) {
            return null;
        }

        if (($parsedPayload['format'] ?? null) === 'v2-offline') {
            $snapshot = $parsedPayload['snapshot'] ?? null;

            if (! is_array($snapshot)) {
                return null;
            }

            $flight = Flight::find((int) $parsedPayload['flight_id']);
            $status = $flight?->status instanceof FlightPlanStatus
                ? $flight->status
                : FlightPlanStatus::tryFrom((string) ($flight?->status ?? ''));

            return [
                'id' => (int) $parsedPayload['flight_id'],
                'aircraft_identification' => (string) ($snapshot['aircraft_identification'] ?? 'N/A'),
                'date_of_flight' => $this->formatFlightDateForLookup($snapshot['date_of_flight'] ?? null),
                'proposed_time' => UtcFourDigitTime::formatForDisplay($snapshot['proposed_time'] ?? null) ?? 'N/A',
                'departure_aerodrome' => (string) ($snapshot['departure_aerodrome'] ?? 'N/A'),
                'destination_aerodrome' => (string) ($snapshot['destination_aerodrome'] ?? 'N/A'),
                'status' => $status?->value ?? 'verified_qr_only',
                'status_label' => $status?->label() ?? 'Valid QR. Needs ATC Review.',
                'status_color' => $status?->filamentColor() ?? 'info',
                'flight' => $flight,
                'preview' => [
                    'payload' => $parsedPayload['normalized_payload'],
                    'snapshot' => $snapshot,
                    'flight_id' => $parsedPayload['flight_id'],
                    'issued_at' => $parsedPayload['issued_at'],
                    'key_id' => $parsedPayload['key_id'],
                    'schema_id' => $parsedPayload['schema_id'],
                ],
            ];
        }

        $flight = Flight::find((int) $parsedPayload['flight_id']);

        if (! $flight) {
            return null;
        }

        $status = $flight->status instanceof FlightPlanStatus
            ? $flight->status
            : FlightPlanStatus::tryFrom((string) $flight->status);

        return [
            'id' => $flight->getKey(),
            'aircraft_identification' => (string) ($flight->aircraft_identification ?? 'N/A'),
            'date_of_flight' => $this->formatFlightDateForLookup($flight->date_of_flight),
            'proposed_time' => UtcFourDigitTime::formatForDisplay($flight->proposed_time) ?? 'N/A',
            'departure_aerodrome' => (string) ($flight->departure_aerodrome ?? 'N/A'),
            'destination_aerodrome' => (string) ($flight->destination_aerodrome ?? 'N/A'),
            'status' => $status?->value ?? (string) ($flight->status ?? 'unknown'),
            'status_label' => $status?->label() ?? str((string) ($flight->status ?? 'unknown'))->headline()->toString(),
            'status_color' => $status?->filamentColor() ?? 'gray',
            'flight' => $flight,
            'preview' => null,
        ];
    }

    private function formatFlightDateForLookup(mixed $value): string
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
}
