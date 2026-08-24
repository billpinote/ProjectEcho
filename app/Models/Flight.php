<?php

namespace App\Models;

use App\Domain\FlightPlans\Enums\FlightPlanStatus;
use App\Domain\FlightPlans\Rules\UtcFourDigitTime;
use App\Domain\FlightPlans\Support\FlightAccess;
use App\Domain\Users\Enums\UserRole;
use Carbon\CarbonInterface;
use Database\Factories\FlightFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class Flight extends Model
{
    private const OPERATIONS_TIMEZONE = 'Asia/Manila';

    private const MINUTE_PRECISION_TIME_FIELDS = [
        'proposed_time',
        'total_eet',
        'endurance',
        'time_start_up',
        'time_shutdown',
        'time_block_off',
        'time_block_on',
        'time_airborne',
        'time_touchdown',
        'received_time',
    ];

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
        'cancelled_at' => 'datetime',
        'pic_authorized_at' => 'datetime',
        'pic_authorization_token_expires_at' => 'datetime',
        'revision_number' => 'integer',
        'revision_of_id' => 'integer',
        'pic_authorized_revision' => 'integer',
        'pic_authorization_declined_at' => 'datetime',
        'pic_authorization_archived_at' => 'datetime',
    ];

    protected $fillable = [
        'user_id',
        'filed_by_user_id',
        'prepared_by_user_id',
        'prepared_by_name',
        'prepared_by_role',
        'operator_id',
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
        'pilot_id',
        'pilot_in_command_user_id',
        'filed_by_name',
        'filed_by_signature',
        'pilot_license_no',
        'pilot_ratings',
        'license_expiry_date',
        'pic_authorized_by_user_id',
        'pic_authorized_at',
        'pic_authorization_method',
        'pic_authorization_token',
        'pic_authorization_token_expires_at',
        'revision_number',
        'revision_of_id',
        'pic_authorized_revision',
        'pic_authorization_status',
        'pic_authorization_declined_by_user_id',
        'pic_authorization_declined_at',
        'pic_authorization_decline_reason',
        'pic_authorization_archived_at',
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
        'cancelled_by_user_id',
        'accepted_by_wiresign',
        'rejected_by_wiresign',
        'rejection_reason',
        'status',
        'reviewed_at',
        'cancelled_at',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $flight): void {
            $flight->normalizeMinutePrecisionTimes();
        });
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_user_id');
    }

    public function filedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'filed_by_user_id');
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by_user_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(FlightPlanEvent::class);
    }

    public function revisionOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'revision_of_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(self::class, 'revision_of_id');
    }

    public function pilot(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pilot_id');
    }

    public function pilotInCommandUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pilot_in_command_user_id');
    }

    public function picAuthorizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_authorized_by_user_id');
    }

    public function picAuthorizationDeclinedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_authorization_declined_by_user_id');
    }

    public function scopePendingActive(Builder $query): Builder
    {
        $today = $this->currentOperationsDate();

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
        $today = $this->currentOperationsDate();

        return $query
            ->where('status', FlightPlanStatus::Pending)
            ->whereNotNull('date_of_flight')
            ->whereDate('date_of_flight', '<', $today);
    }

    public function scopeAccepted(Builder $query): Builder
    {
        return $query->where('status', FlightPlanStatus::Accepted);
    }

    public function scopeReady(Builder $query): Builder
    {
        return $query
            ->accepted()
            ->whereNull('time_start_up')
            ->whereNull('time_block_off')
            ->whereNull('time_airborne')
            ->whereNull('time_touchdown')
            ->whereNull('time_block_on')
            ->whereNull('time_shutdown');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->accepted()
            ->where(function (Builder $query): void {
                $query
                    ->whereNotNull('time_start_up')
                    ->orWhereNotNull('time_block_off');
            })
            ->whereNull('time_airborne')
            ->whereNull('time_touchdown')
            ->whereNull('time_block_on')
            ->whereNull('time_shutdown');
    }

    public function scopeAirborne(Builder $query): Builder
    {
        return $query
            ->accepted()
            ->where(function (Builder $query): void {
                $query
                    ->whereNotNull('time_start_up')
                    ->orWhereNotNull('time_block_off');
            })
            ->whereNotNull('time_airborne')
            ->whereNull('time_touchdown')
            ->whereNull('time_block_on')
            ->whereNull('time_shutdown');
    }

    public function scopeLanded(Builder $query): Builder
    {
        return $query
            ->accepted()
            ->where(function (Builder $query): void {
                $query
                    ->whereNotNull('time_start_up')
                    ->orWhereNotNull('time_block_off');
            })
            ->whereNotNull('time_airborne')
            ->whereNotNull('time_touchdown')
            ->whereNull('time_block_on')
            ->whereNull('time_shutdown');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query
            ->accepted()
            ->where(function (Builder $query): void {
                $query
                    ->whereNotNull('time_start_up')
                    ->orWhereNotNull('time_block_off');
            })
            ->whereNotNull('time_airborne')
            ->whereNotNull('time_touchdown')
            ->where(function (Builder $query): void {
                $query
                    ->whereNotNull('time_block_on')
                    ->orWhereNotNull('time_shutdown');
            });
    }

    public function scopeCurrentForPilot(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query
                ->pendingActive()
                ->orWhere(fn (Builder $query): Builder => $query->ready())
                ->orWhere(fn (Builder $query): Builder => $query->active())
                ->orWhere(fn (Builder $query): Builder => $query->airborne())
                ->orWhere(fn (Builder $query): Builder => $query->landed());
        });
    }

    public function scopeArchivedForPilot(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query
                ->pendingExpired()
                ->orWhere(fn (Builder $query): Builder => $query->rejected())
                ->orWhere('pic_authorization_status', 'declined')
                ->orWhere('status', FlightPlanStatus::Cancelled);
        });
    }

    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        return FlightAccess::restrictQueryToVisibleFlights($query, $user);
    }

    public function scopeAwaitingPicAuthorization(Builder $query): Builder
    {
        return $query
            ->where(function (Builder $query): void {
                $query
                    ->whereHas('preparedBy', fn (Builder $query): Builder => $query->where('role', UserRole::OperatorStaff->value))
                    ->orWhere(function (Builder $query): void {
                        $query
                            ->where(function (Builder $query): void {
                                $query
                                    ->whereNull('prepared_by_user_id')
                                    ->whereNotNull('pilot_in_command_user_id');
                            })
                            ->orWhere(function (Builder $query): void {
                                $query
                                    ->whereNotNull('prepared_by_user_id')
                                    ->whereNull('pilot_in_command_user_id');
                            })
                            ->orWhere(function (Builder $query): void {
                                $query
                                    ->whereNotNull('prepared_by_user_id')
                                    ->whereNotNull('pilot_in_command_user_id')
                                    ->whereColumn('prepared_by_user_id', '!=', 'pilot_in_command_user_id');
                            });
                    });
            })
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('pic_authorized_by_user_id')
                    ->orWhereNull('pic_authorized_at')
                    ->orWhereNull('pic_authorized_revision')
                    ->orWhereRaw('pic_authorized_revision != COALESCE(revision_number, 1)');
            })
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('pic_authorization_status')
                    ->orWhere('pic_authorization_status', '!=', 'declined')
                    ->orWhere(function (Builder $query): void {
                        $query
                            ->where('pic_authorization_status', 'declined')
                            ->whereNull('pic_authorization_archived_at')
                            ->where('pic_authorization_declined_at', '>', now()->subHours(6))
                            ->whereDoesntHave('revisions');
                    });
            });
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

        return $this->resolveDateOfFlight()?->isBefore($this->operationsToday()) ?? false;
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

    public function requiresPicAuthorization(): bool
    {
        if ($this->preparedBy?->isOperatorStaff()) {
            return true;
        }

        if ($this->prepared_by_user_id === null && $this->pilot_in_command_user_id === null) {
            return false;
        }

        if ($this->pilot_in_command_user_id === null) {
            return true;
        }

        if ($this->prepared_by_user_id === null) {
            return true;
        }

        return (int) $this->prepared_by_user_id !== (int) $this->pilot_in_command_user_id;
    }

    public function isPicAuthorizationDeclined(): bool
    {
        return $this->pic_authorization_status === 'declined';
    }

    public function isPicAuthorized(): bool
    {
        return $this->pic_authorized_by_user_id !== null && $this->pic_authorized_at !== null;
    }

    public function isPicAuthorizationCurrent(): bool
    {
        return $this->isPicAuthorized()
            && $this->pic_authorized_revision !== null
            && (int) $this->pic_authorized_revision === (int) ($this->revision_number ?? 1);
    }

    public function canSubmitToAtc(): bool
    {
        if ($this->isPicAuthorizationDeclined()) {
            return false;
        }

        if (! $this->requiresPicAuthorization()) {
            return true;
        }

        return $this->pilot_in_command_user_id !== null && $this->isPicAuthorizationCurrent();
    }

    public function invalidatePicAuthorization(): void
    {
        $this->forceFill([
            'pic_authorized_by_user_id' => null,
            'pic_authorized_at' => null,
            'pic_authorization_method' => null,
            'pic_authorization_token' => null,
            'pic_authorization_token_expires_at' => null,
            'pic_authorized_revision' => null,
        ]);

        if ($this->exists) {
            $this->saveQuietly();
        }
    }

    public function incrementRevisionNumber(): void
    {
        $this->forceFill([
            'revision_number' => max(1, (int) ($this->revision_number ?? 1)) + 1,
        ]);

        if ($this->exists) {
            $this->saveQuietly();
        }
    }

    public function archivePicDecline(): void
    {
        $this->forceFill(['pic_authorization_archived_at' => now()])->saveQuietly();
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

        return Carbon::parse($this->date_of_flight, self::OPERATIONS_TIMEZONE)->startOfDay();
    }

    private function operationsToday(): CarbonInterface
    {
        return now(self::OPERATIONS_TIMEZONE)->startOfDay();
    }

    private function currentOperationsDate(): string
    {
        return now(self::OPERATIONS_TIMEZONE)->toDateString();
    }

    private function normalizeMinutePrecisionTimes(): void
    {
        foreach (self::MINUTE_PRECISION_TIME_FIELDS as $field) {
            if (! array_key_exists($field, $this->attributes)) {
                continue;
            }

            $this->attributes[$field] = UtcFourDigitTime::normalizeDatabaseTime($this->attributes[$field]);
        }
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->filed_by_user_id !== null && $this->filed_by_user_id === $user->getKey();
    }

    public function isPilotInvolved(User $user): bool
    {
        $userId = (int) $user->getKey();

        return in_array($userId, array_map(
            static fn (mixed $value): int => (int) $value,
            [
                $this->filed_by_user_id,
                $this->prepared_by_user_id,
                $this->pilot_in_command_user_id,
                $this->pilot_id,
            ],
        ), true);
    }

    public function isPilotInCommand(User $user): bool
    {
        return $this->pilot_in_command_user_id !== null
            && (int) $this->pilot_in_command_user_id === (int) $user->getKey();
    }

    public function hasOperationalActivity(): bool
    {
        return filled($this->time_start_up)
            || filled($this->time_block_off)
            || filled($this->time_airborne);
    }

    public function canBeDelayedByPilot(): bool
    {
        if ($this->status === FlightPlanStatus::Cancelled || $this->status === FlightPlanStatus::Rejected) {
            return false;
        }

        if ($this->status === FlightPlanStatus::Completed || $this->status === FlightPlanStatus::Active) {
            return false;
        }

        if ($this->isPendingExpired()) {
            return false;
        }

        return ! $this->hasOperationalActivity();
    }

    public function canBeCancelledByPilot(): bool
    {
        if ($this->status === FlightPlanStatus::Cancelled) {
            return false;
        }

        if ($this->status === FlightPlanStatus::Rejected || $this->status === FlightPlanStatus::Completed) {
            return false;
        }

        if ($this->isPendingExpired()) {
            return false;
        }

        return ! $this->hasOperationalActivity();
    }
}
