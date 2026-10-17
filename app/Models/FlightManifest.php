<?php

namespace App\Models;

use Database\Factories\FlightManifestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class FlightManifest extends Model
{
    /** @use HasFactory<FlightManifestFactory> */
    use HasFactory;

    protected $fillable = [
        'aircraft_owner_name', 'aircraft_owner_contact_number', 'home_base_hangar',
        'coa_expiry_date', 'cargo_type', 'cargo_quantity', 'total_cargo_weight', 'remarks',
    ];

    protected function casts(): array
    {
        return ['coa_expiry_date' => 'date', 'total_cargo_weight' => 'decimal:3'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $manifest): void {
            $manifest->ensureEditable();
            if ($manifest->exists && $manifest->isDirty(['flight_id', 'created_by_user_id'])) {
                throw ValidationException::withMessages(['manifest' => 'A manifest cannot be reassigned to another flight or creator.']);
            }
        });
        static::deleting(fn (self $manifest) => $manifest->ensureEditable());
    }

    public function flight(): BelongsTo
    {
        return $this->belongsTo(Flight::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function persons(): HasMany
    {
        return $this->hasMany(ManifestPerson::class)->orderBy('sequence_number')->orderBy('id');
    }

    public function ensureEditable(): void
    {
        $flightId = $this->exists ? $this->getRawOriginal('flight_id') : $this->flight_id;
        if (Flight::query()->findOrFail($flightId)->hasBeenAccepted()) {
            throw ValidationException::withMessages(['manifest' => 'This accepted manifest is read-only. Create a new flight plan and manifest for changes.']);
        }
    }
}
