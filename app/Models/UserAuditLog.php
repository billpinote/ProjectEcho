<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class UserAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'performed_by_user_id',
        'action',
        'source',
        'field',
        'old_value',
        'new_value',
        'auditable_type',
        'auditable_id',
        'profile_update_request_id',
        'changes',
        'description',
        'remarks',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_user_id');
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
