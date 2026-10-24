<?php

namespace App\Models;

use Database\Factories\PilotSignatureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class PilotSignature extends Model
{
    /** @use HasFactory<PilotSignatureFactory> */
    use HasFactory;

    protected $guarded = ['*'];

    protected $hidden = ['stored_path'];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'version' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $signature): void {
            if ($signature->isDirty(['user_id', 'profile_update_request_id', 'version', 'stored_path', 'sha256', 'source', 'width', 'height', 'file_size', 'submitted_at'])) {
                throw ValidationException::withMessages(['signature' => 'Signature versions are immutable. Submit a new version.']);
            }
        });
        static::deleting(function (): void {
            throw ValidationException::withMessages(['signature' => 'Signature versions and review history must be preserved.']);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ProfileUpdateRequest::class, 'profile_update_request_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function reference(): string
    {
        return 'Signature #'.$this->getKey().' v'.$this->version.' SHA256 '.$this->sha256;
    }
}
