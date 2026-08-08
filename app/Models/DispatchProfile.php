<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispatchProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'dispatcher_license_number',
        'dispatcher_certificate',
        'department',
        'position',
        'office_phone',
        'mobile_number',
        'shift',
        'remarks',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
