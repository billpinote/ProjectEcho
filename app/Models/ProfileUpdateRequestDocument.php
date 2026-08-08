<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfileUpdateRequestDocument extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'profile_update_request_id',
        'original_filename',
        'stored_path',
        'mime_type',
        'file_size',
        'uploaded_at',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ProfileUpdateRequest::class, 'profile_update_request_id');
    }
}
