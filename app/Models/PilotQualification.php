<?php

namespace App\Models;

use App\Domain\Pilots\Enums\PilotQualificationCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PilotQualification extends Model
{
    use HasFactory;

    protected $fillable = [
        'pilot_profile_id',
        'category',
        'code',
        'description',
        'expiry_date',
        'remarks',
    ];

    protected $casts = [
        'category' => PilotQualificationCategory::class,
        'expiry_date' => 'date',
    ];

    public function pilotProfile(): BelongsTo
    {
        return $this->belongsTo(PilotProfile::class);
    }
}
