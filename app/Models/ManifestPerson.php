<?php

namespace App\Models;

use Database\Factories\ManifestPersonFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class ManifestPerson extends Model
{
    /** @use HasFactory<ManifestPersonFactory> */
    use HasFactory;

    protected $table = 'manifest_persons';

    protected $fillable = ['sequence_number', 'full_name', 'nationality', 'classification'];

    protected function casts(): array
    {
        return ['sequence_number' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $person): void {
            $person->ensureEditable();
            if ($person->exists && $person->isDirty('flight_manifest_id')) {
                throw ValidationException::withMessages(['persons' => 'A person cannot be moved to another manifest.']);
            }
        });
        static::deleting(fn (self $person) => $person->ensureEditable());
    }

    public function flightManifest(): BelongsTo
    {
        return $this->belongsTo(FlightManifest::class);
    }

    private function ensureEditable(): void
    {
        $manifestId = $this->exists ? $this->getRawOriginal('flight_manifest_id') : $this->flight_manifest_id;
        FlightManifest::query()->findOrFail($manifestId)->ensureEditable();
    }
}
