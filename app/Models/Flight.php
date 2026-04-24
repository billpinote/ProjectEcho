<?php

namespace App\Models;

use App\Enums\FlightPlanStatus;
use Carbon\CarbonInterface;
use Database\Factories\FlightFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class Flight extends Model
{
    /** @use HasFactory<FlightFactory> */
    use HasFactory;

    protected $casts = [
        'emergency_radio_uhf' => 'boolean',
        'emergency_radio_vhf' => 'boolean',
        'emergency_radio_elt' => 'boolean',
        'survival_equipment_polar' => 'boolean',
        'survival_equipment_desert' => 'boolean',
        'survival_equipment_maritime' => 'boolean',
        'survival_equipment_jungle' => 'boolean',
        'jackets_light' => 'boolean',
        'jackets_fluores' => 'boolean',
        'jackets_uhf' => 'boolean',
        'jackets_vhf' => 'boolean',
        'dinghies_enabled' => 'boolean',
        'authorized_representative_enabled' => 'boolean',
        'status' => FlightPlanStatus::class,
        'reviewed_at' => 'datetime',
    ];

    protected $fillable = [
        'time_start_up',
        'time_shutdown',
        'time_block_off',
        'time_block_on',
        'time_airborne',
        'time_touchdown',
        'addressees',
        'originator',
        'date_of_filing',
        'date_of_flight',
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
        'flight_crew_and_passengers',
        'destination_aerodrome',
        'total_eet',
        'altn_aerodrome_1',
        'altn_aerodrome_2',
        'other_info',
        'other_information',
        'other_info_rmk',
        'other_info_pbn',
        'other_info_route',
        'other_info_dep',
        'other_info_dest',
        'other_info_typ',
        'other_info_reg',
        'other_info_altn_1',
        'other_info_altn_2',
        'other_info_opr',
        'other_info_airworthiness',
        'other_info_expiry_date_to_operate',
        'other_info_dof',
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
        'filed_by_name',
        'filed_by_signature',
        'pilot_license_no',
        'pilot_ratings',
        'license_expiry_date',
        'authorized_representative_enabled',
        'authorized_representative_name',
        'authorized_representative_role',
        'authorized_representative_id_license',
        'authorized_representative_expiry_date',
        'received_by',
        'received_date',
        'received_time',
        'received_facility',
        'accepted_by_user_id',
        'accepted_by_wiresign',
        'rejected_by_wiresign',
        'rejection_reason',
        'status',
        'reviewed_at',
    ];

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_user_id');
    }

    public function scopePendingActive(Builder $query): Builder
    {
        $today = now()->toDateString();

        return $query
            ->where('status', FlightPlanStatus::Pending)
            ->where(function (Builder $query) use ($today): void {
                $query
                    ->whereNull('date_of_flight')
                    ->orWhereDate('date_of_flight', '>=', $today);
            });
    }

    public function scopePendingExpired(Builder $query): Builder
    {
        $today = now()->toDateString();

        return $query
            ->where('status', FlightPlanStatus::Pending)
            ->whereNotNull('date_of_flight')
            ->whereDate('date_of_flight', '<', $today);
    }

    public function scopeAccepted(Builder $query): Builder
    {
        return $query->where('status', FlightPlanStatus::Accepted);
    }

    public function scopePendingUnreviewed(Builder $query): Builder
    {
        if (! static::hasReviewedAtColumn()) {
            return $query->pendingActive();
        }

        return $query
            ->pendingActive()
            ->whereNull('reviewed_at');
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', FlightPlanStatus::Rejected);
    }

    public function isPendingExpired(): bool
    {
        if ($this->status !== FlightPlanStatus::Pending || blank($this->date_of_flight)) {
            return false;
        }

        return $this->resolveDateOfFlight()?->isBefore(today()) ?? false;
    }

    public function getExpirationReasonAttribute(): ?string
    {
        if (! $this->isPendingExpired()) {
            return null;
        }

        $dateOfFlight = $this->resolveDateOfFlight();

        if (! $dateOfFlight instanceof CarbonInterface) {
            return 'Expired due to date of flight.';
        }

        return sprintf(
            'Expired due to DOF/%s.',
            $dateOfFlight->format('Ymd')
        );
    }

    public function markAsReviewed(): void
    {
        if (! static::hasReviewedAtColumn()) {
            return;
        }

        if ($this->reviewed_at !== null) {
            return;
        }

        $this->forceFill([
            'reviewed_at' => now(),
        ])->saveQuietly();
    }

    public static function hasReviewedAtColumn(): bool
    {
        static $hasReviewedAtColumn;

        return $hasReviewedAtColumn ??= Schema::hasColumn((new static)->getTable(), 'reviewed_at');
    }

    private function resolveDateOfFlight(): ?CarbonInterface
    {
        if (blank($this->date_of_flight)) {
            return null;
        }

        return \Illuminate\Support\Carbon::parse($this->date_of_flight);
    }
}
