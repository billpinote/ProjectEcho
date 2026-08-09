<?php

namespace App\Models;

use App\Domain\Pilots\Enums\PilotLicenseType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PilotProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'license_type',
        'license_number',
        'ratings',
        'license_expiry_date',
        'medical_expiry_date',
        'operator',
        'remarks',
    ];

    protected $casts = [
        'license_type' => PilotLicenseType::class,
        'license_expiry_date' => 'date',
        'medical_expiry_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function qualifications(): HasMany
    {
        return $this->hasMany(PilotQualification::class);
    }
}
