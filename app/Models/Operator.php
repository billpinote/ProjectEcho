<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Operator extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'icao_code',
        'certificate_number',
        'address',
        'contact_number',
        'email',
        'remarks',
    ];

    protected $casts = [
        'remarks' => 'string',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
