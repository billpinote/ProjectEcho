<?php

namespace App\Filament\Panels\Pilot\Widgets;

use App\Domain\FlightPlans\Support\FlightStatusDisplay;
use App\Models\Flight;
use App\Models\User;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class PilotDashboardWidget extends Widget
{
    private const EXPIRING_SOON_DAYS = 30;

    private const FLIGHT_CARD_LIMIT = 3;

    protected string $view = 'filament.widgets.pilot-dashboard';

    protected static bool $isLazy = false;

    protected static ?int $sort = 1;

    public function getColumns(): int|array
    {
        return 1;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        /** @var User|null $user */
        $user = auth()->user();

        $user?->loadMissing(['operator', 'pilotProfile.qualifications']);

        return [
            'readiness' => $this->readinessData($user),
            'flightSections' => $this->flightSections($user),
            'recentFlights' => $this->recentFlights($user),
            'fileFlightPlanUrl' => route('filament.pilot.resources.flights.create'),
            'allFlightsUrl' => route('filament.pilot.resources.my-flight-plans.index'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function readinessData(?User $user): array
    {
        $profile = $user?->pilotProfile;
        $licenceStatus = $this->expiryStatus($profile?->license_expiry_date);
        $medicalStatus = $this->expiryStatus($profile?->medical_expiry_date);
        $today = now()->startOfDay();

        return [
            'greeting' => $this->greeting(),
            'friendly_name' => $this->friendlyName($user),
            'licence' => $profile?->formattedLicense() ?: 'Licence not recorded',
            'operator' => $user?->operator?->name ?: 'No operator assigned',
            'licence_status' => $licenceStatus,
            'medical_status' => $medicalStatus,
            'active_qualifications' => $profile?->qualifications
                ->filter(fn ($qualification): bool => $qualification->expiry_date === null || Carbon::parse($qualification->expiry_date)->startOfDay()->gte($today))
                ->count() ?? 0,
            'attention' => array_values(array_filter([
                $licenceStatus['message'] ?? null,
                $medicalStatus['message'] ?? null,
            ])),
        ];
    }

    private function greeting(): string
    {
        $hour = now()->hour;

        if ($hour < 12) {
            return 'Good morning';
        }

        if ($hour < 18) {
            return 'Good afternoon';
        }

        return 'Good evening';
    }

    private function friendlyName(?User $user): string
    {
        $displayName = trim((string) ($user?->display_name ?? ''));

        if ($displayName !== '') {
            return $displayName;
        }

        $firstName = trim((string) ($user?->first_name ?? ''));

        return $firstName !== '' ? $firstName : trim((string) ($user?->name ?? 'Pilot'));
    }

    /**
     * @return array{label: string, color: string, message?: string}
     */
    private function expiryStatus(mixed $date): array
    {
        if ($date === null) {
            return ['label' => 'Not Recorded', 'color' => 'gray'];
        }

        $expiry = Carbon::parse($date)->startOfDay();
        $today = now()->startOfDay();

        if ($expiry->lt($today)) {
            return ['label' => 'Expired', 'color' => 'danger', 'message' => 'Expired'];
        }

        $days = $today->diffInDays($expiry);

        if ($days <= self::EXPIRING_SOON_DAYS) {
            return [
                'label' => 'Expiring Soon',
                'color' => 'warning',
                'message' => 'Expires in '.$days.' '.str('day')->plural($days),
            ];
        }

        return ['label' => 'Valid', 'color' => 'success'];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function flightSections(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        return [
            [
                'key' => 'pending',
                'heading' => 'Pending',
                'empty' => 'No pending flight plans.',
                'flights' => $this->flightQuery($user)
                    ->pendingActive()
                    ->orderBy('date_of_flight')
                    ->orderBy('proposed_time')
                    ->limit(self::FLIGHT_CARD_LIMIT)
                    ->get()
                    ->map(fn (Flight $flight): array => $this->flightCard($flight, 'View', route('flights.view', $flight)))
                    ->all(),
            ],
            [
                'key' => 'accepted',
                'heading' => 'Accepted',
                'empty' => 'No accepted flights ready for departure.',
                'flights' => $this->flightQuery($user)
                    ->ready()
                    ->orderBy('date_of_flight')
                    ->orderBy('proposed_time')
                    ->limit(self::FLIGHT_CARD_LIMIT)
                    ->get()
                    ->map(fn (Flight $flight): array => $this->flightCard($flight, 'Show QR', route('flights.qr', $flight)))
                    ->all(),
            ],
            [
                'key' => 'active',
                'heading' => 'Active',
                'empty' => 'No active flights right now.',
                'flights' => $this->flightQuery($user)
                    ->where(function (Builder $query): void {
                        $query
                            ->active()
                            ->orWhere(fn (Builder $query): Builder => $query->airborne())
                            ->orWhere(fn (Builder $query): Builder => $query->landed());
                    })
                    ->orderBy('date_of_flight')
                    ->orderBy('proposed_time')
                    ->limit(self::FLIGHT_CARD_LIMIT)
                    ->get()
                    ->map(fn (Flight $flight): array => $this->flightCard($flight, 'View', route('flights.view', $flight)))
                    ->all(),
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentFlights(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        return $this->flightQuery($user)
            ->completed()
            ->orderByDesc('date_of_flight')
            ->orderByDesc('proposed_time')
            ->limit(self::FLIGHT_CARD_LIMIT)
            ->get()
            ->map(fn (Flight $flight): array => $this->flightCard($flight, 'View', route('flights.view', $flight), subdued: true))
            ->all();
    }

    private function flightQuery(User $user): Builder
    {
        return Flight::query()
            ->select([
                'id',
                'filed_by_user_id',
                'aircraft_identification',
                'departure_aerodrome',
                'destination_aerodrome',
                'date_of_flight',
                'proposed_time',
                'type_of_aircraft',
                'flight_rules',
                'status',
                'time_start_up',
                'time_block_off',
                'time_airborne',
                'time_touchdown',
                'time_block_on',
                'time_shutdown',
            ])
            ->where('filed_by_user_id', $user->id);
    }

    /**
     * @return array<string, mixed>
     */
    private function flightCard(Flight $flight, string $actionLabel, string $actionUrl, bool $subdued = false): array
    {
        $status = FlightStatusDisplay::badge($flight, auth()->user()?->role);

        return [
            'callsign' => $flight->aircraft_identification ?: 'Unassigned',
            'departure' => $flight->departure_aerodrome ?: '----',
            'destination' => $flight->destination_aerodrome ?: '----',
            'date' => $flight->date_of_flight
                ? Carbon::parse($flight->date_of_flight)->format('d M Y')
                : 'Date TBD',
            'time' => $this->formatUtcTime($flight->proposed_time),
            'aircraft_type' => $flight->type_of_aircraft ?: 'Type TBD',
            'flight_rules' => $flight->flight_rules ?: 'Rules TBD',
            'status' => $status,
            'action_label' => $actionLabel,
            'action_url' => $actionUrl,
            'subdued' => $subdued,
        ];
    }

    private function formatUtcTime(?string $time): string
    {
        $time = trim((string) $time);

        if ($time === '') {
            return 'Time TBD';
        }

        return str_replace(':', '', substr($time, 0, 5)).'Z';
    }
}
