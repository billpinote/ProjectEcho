<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'provider',
        'identifier',
        'password_hash',
        'provider_user_id',
        'email',
        'email_verified_at',
        'last_login_at',
        'last_login_ip',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
