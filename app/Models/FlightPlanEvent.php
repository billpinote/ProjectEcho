<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlightPlanEvent extends Model
{
    public const TYPE_CREATED = 'flight_created';

    public const TYPE_SUBMITTED = 'submitted';

    public const TYPE_SUBMITTED_FOR_PIC_AUTHORIZATION = 'submitted_for_pic_authorization';

    public const TYPE_PIC_AUTHORIZED = 'pic_authorized';

    public const TYPE_PIC_DECLINED = 'pic_declined';

    public const TYPE_SUBMITTED_TO_ATC = 'submitted_to_atc';

    public const TYPE_ATC_ACCEPTED = 'atc_accepted';

    public const TYPE_ATC_REJECTED = 'atc_rejected';

    public const TYPE_DELAYED = 'delayed';

    public const TYPE_CANCELLED = 'cancelled';

    public const TYPE_ARCHIVED = 'archived';

    public const TYPE_STARTUP_RECORDED = 'startup_recorded';

    public const TYPE_BLOCK_OFF_RECORDED = 'block_off_recorded';

    public const TYPE_AIRBORNE = 'airborne';

    public const TYPE_TOUCHDOWN = 'touchdown';

    public const TYPE_SHUTDOWN_RECORDED = 'shutdown_recorded';

    public const TYPE_FLIGHT_COMPLETED = 'flight_completed';

    public $timestamps = false;

    protected $fillable = [
        'flight_id',
        'actor_user_id',
        'event_type',
        'old_values',
        'new_values',
        'reason',
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public static function record(Flight $flight, string $eventType, ?User $actor = null, ?array $oldValues = null, ?array $newValues = null, ?string $reason = null): self
    {
        $newValues ??= [];

        if ($actor !== null) {
            $newValues['actor_name'] ??= $actor->name;
            $newValues['actor_role'] ??= $actor->role?->value ?? $actor->role;
        }

        return self::create([
            'flight_id' => $flight->getKey(),
            'actor_user_id' => $actor?->getKey(),
            'event_type' => $eventType,
            'old_values' => $oldValues,
            'new_values' => $newValues === [] ? null : $newValues,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }

    public function flight(): BelongsTo
    {
        return $this->belongsTo(Flight::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
