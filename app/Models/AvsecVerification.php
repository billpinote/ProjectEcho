<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class AvsecVerification extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    protected $hidden = ['approved_content'];

    protected function casts(): array
    {
        return ['verified_at' => 'immutable_datetime', 'flight_revision' => 'integer', 'approved_content' => 'encrypted:array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw ValidationException::withMessages(['avsec' => 'AVSEC confirmations are permanent and immutable.']));
        static::deleting(fn () => throw ValidationException::withMessages(['avsec' => 'AVSEC confirmations must be preserved.']));
    }

    public function flight(): BelongsTo
    {
        return $this->belongsTo(Flight::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verifier_user_id');
    }
}
