<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AtcProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'wiresign',
        'facility',
        'position',
        'endorsements',
        'remarks',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
