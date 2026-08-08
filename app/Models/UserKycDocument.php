<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserKycDocument extends Model
{
    use HasFactory;

    public const DOCUMENT_TYPES = [
        'company_id' => 'Company ID',
        'caap_id' => 'CAAP ID',
        'government_id' => 'Government-issued ID',
        'pilot_license' => 'Pilot License',
        'drivers_license' => "Driver's License",
        'passport' => 'Passport',
        'operator_certification' => 'Operator/Company Certification',
        'other' => 'Other',
    ];

    protected $fillable = [
        'user_id',
        'document_type',
        'document_identifier',
        'file_path',
        'original_file_name',
        'verified_by_user_id',
        'verified_at',
        'remarks',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    public function documentTypeLabel(): string
    {
        return self::DOCUMENT_TYPES[$this->document_type] ?? str((string) $this->document_type)->headline()->toString();
    }

    public function maskedIdentifier(): ?string
    {
        $identifier = trim((string) ($this->document_identifier ?? ''));

        if ($identifier === '') {
            return null;
        }

        $visible = substr($identifier, -4);

        return str_repeat('*', max(strlen($identifier) - 4, 4)).$visible;
    }
}
