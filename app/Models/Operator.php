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
        'short_name',
        'icao_code',
        'certificate_number',
        'address',
        'contact_number',
        'email',
        'remarks',
        'is_active',
    ];

    protected $casts = [
        'remarks' => 'string',
        'is_active' => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function flights(): HasMany
    {
        return $this->hasMany(Flight::class);
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(OperatorAlias::class);
    }
}
