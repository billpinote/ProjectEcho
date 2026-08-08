<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvsecProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'security_certification',
        'certification_expiry',
        'security_clearance_level',
        'position',
        'remarks',
    ];

    protected $casts = [
        'certification_expiry' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
