<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class FilingAuthorization extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    protected $hidden = ['documents', 'authorized_documents', 'pdf_documents'];

    protected function casts(): array
    {
        return [
            'documents' => 'encrypted:array', 'authorized_documents' => 'encrypted:array',
            'pdf_documents' => 'encrypted:array',
            'authentication' => 'array', 'requested_at' => 'datetime', 'decided_at' => 'datetime',
            'flight_revision' => 'integer', 'signature_version' => 'integer',
            'signed_document_types' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $package): void {
            if ($package->getRawOriginal('status') !== 'pending'
                || $package->isDirty(['flight_id', 'flight_revision', 'documents', 'fingerprint', 'requested_at', 'signed_document_types'])
                || ! in_array($package->status, ['authorized', 'declined'], true)) {
                throw ValidationException::withMessages(['filing' => 'Filing authorization evidence is immutable.']);
            }
        });
        static::deleting(function (): void {
            throw ValidationException::withMessages(['filing' => 'Filing authorization history must be preserved.']);
        });
    }

    public function flight(): BelongsTo
    {
        return $this->belongsTo(Flight::class);
    }

    public function pilotSignature(): BelongsTo
    {
        return $this->belongsTo(PilotSignature::class);
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id');
    }
}
