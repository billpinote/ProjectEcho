<?php

namespace App\Services;

use App\Models\Flight;
use Illuminate\Support\Str;
use phpseclib3\Crypt\EC;
use phpseclib3\Crypt\PublicKeyLoader;

class FlightPlanQrPayloadService
{
    public const PREFIX = 'ECHOFPL';

    public const VERSION = '2';

    public const MODE = 'OFFLINE';

    public const KEY_ID = 'K1';

    public const SCHEMA_ID = 'S1';

    /**
     * The fixed positional field order for schema revision S1.
     *
     * @var array<int, string>
     */
    public const S1_FIELDS = [
        'date_of_filing',
        'date_of_flight',
        'originator',
        'aircraft_identification',
        'flight_rules',
        'type_of_flight',
        'number',
        'type_of_aircraft',
        'wake_turbulence_cat',
        'equipment_10a',
        'equipment_10b',
        'departure_aerodrome',
        'proposed_time',
        'cruising_speed',
        'level',
        'route',
        'destination_aerodrome',
        'total_eet',
        'altn_aerodrome_1',
        'altn_aerodrome_2',
        'other_information',
        'endurance',
        'persons_on_board',
        'emergency_radio_uhf',
        'emergency_radio_vhf',
        'emergency_radio_elt',
        'survival_equipment_polar',
        'survival_equipment_desert',
        'survival_equipment_maritime',
        'survival_equipment_jungle',
        'jackets_light',
        'jackets_fluores',
        'jackets_uhf',
        'jackets_vhf',
        'dinghies_enabled',
        'dinghies_number',
        'dinghies_capacity',
        'dinghies_cover',
        'dinghies_color',
        'aircraft_colour_and_markings',
        'remarks',
        'pilot_in_command',
        'pilot_license_no',
        'pilot_ratings',
        'license_expiry_date',
        'authorized_representative_enabled',
        'authorized_representative_name',
        'authorized_representative_role',
        'authorized_representative_id_license',
        'authorized_representative_expiry_date',
    ];

    private const LEGACY_V1_PATTERN = '/^ECHOFPL\|1\|DB\|(\d+)$/i';

    /**
     * @var array<int, string>
     */
    private const BOOLEAN_FIELDS = [
        'emergency_radio_uhf',
        'emergency_radio_vhf',
        'emergency_radio_elt',
        'survival_equipment_polar',
        'survival_equipment_desert',
        'survival_equipment_maritime',
        'survival_equipment_jungle',
        'jackets_light',
        'jackets_fluores',
        'jackets_uhf',
        'jackets_vhf',
        'dinghies_enabled',
        'authorized_representative_enabled',
    ];

    /**
     * @var array<int, string>
     */
    private const INTEGER_FIELDS = [
        'persons_on_board',
        'dinghies_number',
        'dinghies_capacity',
    ];

    public function buildPayload(Flight $flight): ?string
    {
        if (! $flight->exists || $flight->getKey() === null) {
            return null;
        }

        $flightId = (int) $flight->getKey();
        $issuedAt = now('UTC')->format('Ymd\THis\Z');
        $snapshot = $this->buildSnapshot($flight);
        $encodedPayload = $this->encodeSnapshot($snapshot, self::SCHEMA_ID);
        $signedMessage = $this->buildSignedMessage(
            flightId: $flightId,
            issuedAt: $issuedAt,
            encodedPayload: $encodedPayload,
        );
        $signature = $this->base64UrlEncode($this->sign($signedMessage));

        return $signedMessage.'|'.$signature;
    }

    /**
     * Parse and verify either the legacy V1 payload or the new V2 offline payload.
     *
     * @return array<string, mixed>|null
     */
    public function parsePayload(string $payload): ?array
    {
        $normalizedPayload = trim($payload);

        if (preg_match(self::LEGACY_V1_PATTERN, $normalizedPayload, $matches) === 1) {
            return [
                'format' => 'v1-db',
                'normalized_payload' => strtoupper($normalizedPayload),
                'flight_id' => (int) $matches[1],
            ];
        }

        $parts = explode('|', $normalizedPayload);

        if (count($parts) !== 9) {
            return null;
        }

        [$prefix, $version, $mode, $keyId, $schemaId, $flightId, $issuedAt, $encodedPayload, $encodedSignature] = $parts;

        if (! ctype_digit($flightId) || ! preg_match('/^\d{8}T\d{6}Z$/', $issuedAt)) {
            return null;
        }

        if (
            strtoupper($prefix) !== self::PREFIX
            || $version !== self::VERSION
            || strtoupper($mode) !== self::MODE
            || strtoupper($schemaId) !== self::SCHEMA_ID
            || strtoupper($keyId) !== self::KEY_ID
        ) {
            return null;
        }

        $signature = $this->base64UrlDecode($encodedSignature);

        if ($signature === null) {
            return null;
        }

        $signedMessage = implode('|', array_slice($parts, 0, 8));

        if (! $this->verify($signedMessage, $signature, $keyId)) {
            return null;
        }

        $snapshot = $this->decodeSnapshot($encodedPayload, $schemaId);

        if ($snapshot === null) {
            return null;
        }

        return [
            'format' => 'v2-offline',
            'normalized_payload' => $normalizedPayload,
            'flight_id' => (int) $flightId,
            'issued_at' => $issuedAt,
            'key_id' => $keyId,
            'schema_id' => $schemaId,
            'snapshot' => $snapshot,
        ];
    }

    public function looksLikeSupportedPayload(string $payload): bool
    {
        $normalizedPayload = trim($payload);

        if (preg_match(self::LEGACY_V1_PATTERN, $normalizedPayload) === 1) {
            return true;
        }

        return Str::startsWith(strtoupper($normalizedPayload), self::PREFIX.'|'.self::VERSION.'|'.self::MODE.'|')
            && substr_count($normalizedPayload, '|') >= 8;
    }

    public function invalidPayloadMessage(string $payload): string
    {
        $normalizedPayload = trim($payload);
        $parts = explode('|', $normalizedPayload);

        if (count($parts) !== 9) {
            return 'Expected a valid Echo QR payload. V2 signed offline payloads and legacy V1 database payloads are supported.';
        }

        [$prefix, $version, $mode, $keyId, $schemaId, $flightId, $issuedAt, $encodedPayload, $encodedSignature] = $parts;

        if (
            strtoupper($prefix) !== self::PREFIX
            || $version !== self::VERSION
            || strtoupper($mode) !== self::MODE
            || strtoupper($schemaId) !== self::SCHEMA_ID
            || strtoupper($keyId) !== self::KEY_ID
            || ! ctype_digit($flightId)
            || preg_match('/^\d{8}T\d{6}Z$/', $issuedAt) !== 1
        ) {
            return 'Expected a valid Echo QR payload. V2 signed offline payloads and legacy V1 database payloads are supported.';
        }

        $signature = $this->base64UrlDecode($encodedSignature);

        if ($signature === null) {
            return 'This looks like an Echo V2 signed offline QR payload, but its signature field is malformed.';
        }

        $signedMessage = implode('|', array_slice($parts, 0, 8));

        if (! $this->verify($signedMessage, $signature, $keyId)) {
            return 'This looks like an Echo V2 signed offline QR payload, but its Ed25519 signature could not be verified with the current key. It may have been generated by an older sandbox key, a different environment, or a modified QR payload.';
        }

        if ($this->decodeSnapshot($encodedPayload, $schemaId) === null) {
            return 'This Echo V2 QR signature is valid, but the embedded flight plan data could not be decoded.';
        }

        return 'Expected a valid Echo QR payload. V2 signed offline payloads and legacy V1 database payloads are supported.';
    }

    public function currentPublicKeyPem(): string
    {
        $this->ensureKeyPair();

        return (string) file_get_contents($this->publicKeyPath(self::KEY_ID));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSnapshot(Flight $flight): array
    {
        $snapshot = [];

        foreach (self::S1_FIELDS as $field) {
            $snapshot[$field] = $this->normalizeFieldValue(
                $field,
                $flight->getAttribute($field),
            );
        }

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function encodeSnapshot(array $snapshot, string $schemaId): string
    {
        $values = [];

        foreach ($this->fieldsForSchema($schemaId) as $field) {
            $values[] = $snapshot[$field] ?? null;
        }

        $json = json_encode($values, JSON_UNESCAPED_SLASHES);

        if (! is_string($json)) {
            throw new \RuntimeException('Unable to encode QR snapshot JSON.');
        }

        $compressed = gzdeflate($json, 9);

        if ($compressed === false) {
            throw new \RuntimeException('Unable to compress QR snapshot JSON.');
        }

        return $this->base64UrlEncode($compressed);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeSnapshot(string $encodedPayload, string $schemaId): ?array
    {
        $compressed = $this->base64UrlDecode($encodedPayload);

        if ($compressed === null) {
            return null;
        }

        $json = gzinflate($compressed);

        if ($json === false) {
            return null;
        }

        $values = json_decode($json, true);

        if (! is_array($values)) {
            return null;
        }

        $fields = $this->fieldsForSchema($schemaId);

        if (count($values) !== count($fields)) {
            return null;
        }

        $snapshot = [];

        foreach ($fields as $index => $field) {
            $snapshot[$field] = $this->restoreFieldValue($field, $values[$index] ?? null);
        }

        return $snapshot;
    }

    private function buildSignedMessage(int $flightId, string $issuedAt, string $encodedPayload): string
    {
        return implode('|', [
            self::PREFIX,
            self::VERSION,
            self::MODE,
            self::KEY_ID,
            self::SCHEMA_ID,
            (string) $flightId,
            $issuedAt,
            $encodedPayload,
        ]);
    }

    private function sign(string $message): string
    {
        $this->ensureKeyPair();
        $privateKey = PublicKeyLoader::load((string) file_get_contents($this->privateKeyPath(self::KEY_ID)));

        return $privateKey->sign($message);
    }

    private function verify(string $message, string $signature, string $keyId): bool
    {
        $this->ensureKeyPair();
        $publicKey = PublicKeyLoader::load((string) file_get_contents($this->publicKeyPath($keyId)));

        try {
            return $publicKey->verify($message, $signature);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<int, string>
     */
    private function fieldsForSchema(string $schemaId): array
    {
        if ($schemaId !== self::SCHEMA_ID) {
            throw new \InvalidArgumentException('Unsupported QR payload schema: '.$schemaId);
        }

        return self::S1_FIELDS;
    }

    private function normalizeFieldValue(string $field, mixed $value): mixed
    {
        if (in_array($field, self::BOOLEAN_FIELDS, true)) {
            return $value ? 1 : 0;
        }

        if (in_array($field, self::INTEGER_FIELDS, true)) {
            if ($value === null || $value === '') {
                return null;
            }

            return (int) $value;
        }

        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private function restoreFieldValue(string $field, mixed $value): mixed
    {
        if (in_array($field, self::BOOLEAN_FIELDS, true)) {
            return (bool) ((int) ($value ?? 0));
        }

        if (in_array($field, self::INTEGER_FIELDS, true)) {
            if ($value === null || $value === '') {
                return null;
            }

            return (int) $value;
        }

        if ($value === null) {
            return null;
        }

        return (string) $value;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): ?string
    {
        $decoded = base64_decode(
            strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4),
            true,
        );

        return is_string($decoded) ? $decoded : null;
    }

    private function ensureKeyPair(): void
    {
        $directory = $this->keyDirectory();

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $privatePath = $this->privateKeyPath(self::KEY_ID);
        $publicPath = $this->publicKeyPath(self::KEY_ID);

        if (is_file($privatePath) && is_file($publicPath)) {
            return;
        }

        if (is_file($privatePath) && ! is_file($publicPath)) {
            $privateKey = PublicKeyLoader::load((string) file_get_contents($privatePath));
            file_put_contents($publicPath, $privateKey->getPublicKey()->toString('PKCS8'));

            return;
        }

        $privateKey = EC::createKey('Ed25519');

        file_put_contents($privatePath, $privateKey->toString('PKCS8'));
        file_put_contents($publicPath, $privateKey->getPublicKey()->toString('PKCS8'));
    }

    private function keyDirectory(): string
    {
        return storage_path('app/flightplan-qr-keys');
    }

    private function privateKeyPath(string $keyId): string
    {
        return $this->keyDirectory().DIRECTORY_SEPARATOR.Str::lower($keyId).'-private.pem';
    }

    private function publicKeyPath(string $keyId): string
    {
        return $this->keyDirectory().DIRECTORY_SEPARATOR.Str::lower($keyId).'-public.pem';
    }
}
