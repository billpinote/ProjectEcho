<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperatorStaffProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'operator_id',
        'position_title',
        'company_employee_id',
        'authorization_reference',
        'authorization_expiry_date',
        'is_authorized',
        'remarks',
    ];

    protected $casts = [
        'authorization_expiry_date' => 'date',
        'is_authorized' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }
}
