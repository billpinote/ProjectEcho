<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlightPlanEvent extends Model
{
    public const TYPE_SUBMITTED = 'submitted';

    public const TYPE_DELAYED = 'delayed';

    public const TYPE_CANCELLED = 'cancelled';

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

    public function flight(): BelongsTo
    {
        return $this->belongsTo(Flight::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
