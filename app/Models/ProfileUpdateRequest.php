<?php

namespace App\Models;

use App\Domain\ProfileUpdateRequests\Enums\ProfileUpdateRequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProfileUpdateRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'status',
        'requested_changes',
        'reason',
        'submitted_at',
        'reviewed_at',
        'reviewed_by_user_id',
        'reviewer_remarks',
        'rejection_reason',
        'cancelled_at',
    ];

    protected $casts = [
        'status' => ProfileUpdateRequestStatus::class,
        'requested_changes' => 'array',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProfileUpdateRequestDocument::class);
    }

    public function isPending(): bool
    {
        return $this->status === ProfileUpdateRequestStatus::Pending;
    }
}
