<?php

namespace App\Domain\FlightPlans\Support;

use App\Domain\FlightPlans\Services\FlightPlanPdfService;
use App\Domain\FlightPlans\Services\ManifestPdfService;
use App\Models\Flight;
use Closure;

class FilingDocumentRegistry
{
    private const PIC_CREDENTIAL_FIELDS = [
        'pilot_id', 'pilot_in_command_user_id', 'pilot_in_command',
        'pilot_license_no', 'pilot_ratings', 'license_expiry_date',
    ];

    public const OPERATIONAL_FIELDS = [
        'status', 'revised_eobt', 'reviewed_at', 'cancelled_at', 'cancelled_by_user_id',
        'time_start_up', 'time_shutdown', 'time_block_off', 'time_block_on', 'time_airborne', 'time_touchdown',
        'accepted_by_user_id', 'accepted_by_wiresign', 'rejected_by_wiresign', 'rejection_reason',
        'received_by', 'received_date', 'received_time', 'received_facility',
    ];

    public const AUTHORIZATION_FIELDS = [
        'pic_authorized_by_user_id', 'pic_authorized_at', 'pic_authorization_method',
        'pic_authorization_token', 'pic_authorization_token_expires_at', 'pic_authorized_revision',
        'pic_authorization_status', 'pic_authorization_declined_by_user_id', 'pic_authorization_declined_at',
        'pic_authorization_decline_reason', 'pic_authorization_archived_at', 'revision_number', 'revision_of_id',
    ];

    /** @var array<string, Closure(Flight): ?array> */
    private array $documents;

    /** @var array<string, Closure(array, ?string): string> */
    private array $renderers;

    public function __construct()
    {
        $this->documents = [
            'flight_plan' => fn (Flight $flight): array => [
                'id' => $flight->getKey(), 'revision' => (int) ($flight->revision_number ?? 1),
                'content' => $flight->only($this->materialFlightFields()),
            ],
            'passenger_cargo_manifest' => function (Flight $flight): ?array {
                $manifest = $flight->manifest()->lockForUpdate()->first();
                if ($manifest === null) {
                    return null;
                }
                $manifest->setRelation('persons', $manifest->persons()->lockForUpdate()->get());

                return [
                    'id' => $manifest->getKey(),
                    // A content-addressed revision includes ordered persons and cargo.
                    'revision' => self::fingerprint($content = [
                        ...$manifest->only($manifest->getFillable()),
                        'coa_expiry_date' => $manifest->coa_expiry_date?->format('Y-m-d'),
                        'persons' => $manifest->persons->map->only(['sequence_number', 'full_name', 'nationality', 'classification'])->all(),
                    ]),
                    'content' => $content,
                ];
            },
        ];
        $this->renderers = [
            'flight_plan' => function (array $documents, ?string $signature): string {
                $flight = new Flight($documents['flight_plan']['content']);
                $flight->forceFill(['id' => $documents['flight_plan']['id'], 'revision_number' => $documents['flight_plan']['revision']]);

                return app(FlightPlanPdfService::class)->renderSnapshot($flight, $signature, false);
            },
            'passenger_cargo_manifest' => fn (array $documents, ?string $signature): string => app(ManifestPdfService::class)->render([
                ...$documents['flight_plan']['content'], ...$documents['passenger_cargo_manifest']['content'],
            ], $signature),
        ];
    }

    /** @param Closure(Flight): ?array $snapshot */
    public function register(string $type, Closure $snapshot, Closure $renderer): void
    {
        if (isset($this->documents[$type])) {
            throw new \LogicException('A filing document type is already registered.');
        }
        $this->documents[$type] = $snapshot;
        $this->renderers[$type] = $renderer;
    }

    public function render(string $type, array $documents, ?string $signature): string
    {
        if (! isset($this->renderers[$type], $documents[$type])) {
            throw new \LogicException('No renderer is registered for this filing document.');
        }

        return ($this->renderers[$type])($documents, $signature);
    }

    public function matchesReviewedContent(array $reviewed, array $authorized): bool
    {
        foreach (self::PIC_CREDENTIAL_FIELDS as $field) {
            unset($reviewed['flight_plan']['content'][$field], $authorized['flight_plan']['content'][$field]);
        }

        return hash_equals(self::fingerprint($reviewed), self::fingerprint($authorized));
    }

    public function materialFlightFields(): array
    {
        return array_values(array_diff((new Flight)->getFillable(), self::OPERATIONAL_FIELDS, self::AUTHORIZATION_FIELDS));
    }

    public function snapshots(Flight $flight): array
    {
        $flight = $flight->exists ? ($flight->fresh() ?? $flight) : $flight;
        $documents = [];
        foreach ($this->documents as $type => $snapshot) {
            if (($document = $snapshot($flight)) !== null) {
                $documents[$type] = $document;
            }
        }

        return $documents;
    }

    public static function fingerprint(array $value): string
    {
        return hash('sha256', self::canonicalJson($value));
    }

    public static function canonicalJson(array $value): string
    {
        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }
        foreach ($value as &$item) {
            if (is_array($item)) {
                $item = json_decode(self::canonicalJson($item), true, 512, JSON_THROW_ON_ERROR);
            }
        }

        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }
}
