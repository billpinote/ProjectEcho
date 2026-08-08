<?php

namespace App\Models;

use App\Domain\Users\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'display_name',
        'email',
        'username',
        'employee_id',
        'operator_id',
        'wiresign',
        'password',
        'role',
        'station',
        'is_active',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function setRoleAttribute(mixed $value): void
    {
        $this->attributes['role'] = UserRole::normalize($value)?->value ?? UserRole::Pilot->value;
    }

    public function authAccounts(): HasMany
    {
        return $this->hasMany(AuthAccount::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }

    public function pilotProfile(): HasOne
    {
        return $this->hasOne(PilotProfile::class);
    }

    public function atcProfile(): HasOne
    {
        return $this->hasOne(AtcProfile::class);
    }

    public function dispatchProfile(): HasOne
    {
        return $this->hasOne(DispatchProfile::class);
    }

    public function avsecProfile(): HasOne
    {
        return $this->hasOne(AvsecProfile::class);
    }

    public function acceptedFlights(): HasMany
    {
        return $this->hasMany(Flight::class, 'accepted_by_user_id');
    }

    public function filedFlights(): HasMany
    {
        return $this->hasMany(Flight::class, 'filed_by_user_id');
    }

    public function cancelledFlights(): HasMany
    {
        return $this->hasMany(Flight::class, 'cancelled_by_user_id');
    }

    public function pilotFlights(): HasMany
    {
        return $this->hasMany(Flight::class, 'pilot_id');
    }

    public function fullName(): string
    {
        return trim(implode(' ', array_filter([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
            $this->suffix,
        ])));
    }

    public function icaoPilotName(): string
    {
        $parts = array_filter([
            strtoupper(trim((string) $this->first_name)),
            strtoupper(trim((string) $this->middle_name)),
            strtoupper(trim((string) $this->last_name)),
        ]);

        return implode(' ', $parts);
    }

    public function isPilot(): bool
    {
        return $this->role === UserRole::Pilot;
    }

    public function isAtc(): bool
    {
        return $this->role === UserRole::Atmo || $this->role === UserRole::AtsHq;
    }

    public function isDispatch(): bool
    {
        return $this->role === UserRole::Dispatch;
    }

    public function isAvsec(): bool
    {
        return $this->role === UserRole::Avsec;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->role === UserRole::Artisan) {
            return in_array($panel->getId(), [
                'artisan',
                'admin',
                'pilot',
                'atmo',
                'dispatch',
                'avsec',
                'ats',
            ], true);
        }

        return match ($panel->getId()) {
            'admin' => $this->role === UserRole::Admin,
            'pilot' => $this->role === UserRole::Pilot,
            'atmo' => $this->role === UserRole::Atmo && $this->canAccessFlightPanel(),
            'dispatch' => $this->role === UserRole::Dispatch,
            'avsec' => $this->role === UserRole::Avsec,
            'ats' => $this->role === UserRole::AtsHq,
            default => false,
        };
    }

    public function canAccessFlightPanel(): bool
    {
        return match ($this->role) {
            UserRole::Artisan,
            UserRole::Admin,
            UserRole::AtsHq,
            UserRole::Avsec,
            UserRole::Dispatch,
            UserRole::Pilot => true,
            UserRole::Atmo => $this->isRpusStation(),
            default => false,
        };
    }

    public function hasFullFlightAccess(): bool
    {
        return match ($this->role) {
            UserRole::Artisan,
            UserRole::Admin => true,
            UserRole::Atmo => $this->isRpusStation(),
            default => false,
        };
    }

    public function canViewFlightPlans(): bool
    {
        return $this->is_active && $this->canAccessFlightPanel();
    }

    public function canCreateFlightPlans(): bool
    {
        return $this->is_active
            && (
                $this->hasFullFlightAccess()
                || $this->role === UserRole::Dispatch
                || $this->role === UserRole::Pilot
            );
    }

    public function canUpdateFlightPlans(): bool
    {
        return $this->is_active && $this->hasFullFlightAccess();
    }

    public function canDeleteFlightPlans(): bool
    {
        return $this->canUpdateFlightPlans();
    }

    public function canReviewFlightPlans(): bool
    {
        return $this->canUpdateFlightPlans();
    }

    public function canUpdateFlightStartUpTime(): bool
    {
        return $this->canUpdateFlightPlans()
            || ($this->is_active && $this->role === UserRole::Dispatch);
    }

    public function canUpdateFlightBlockOffTime(): bool
    {
        return $this->canUpdateFlightPlans()
            || ($this->is_active && $this->role === UserRole::Dispatch);
    }

    public function canUpdateFlightShutdownTime(): bool
    {
        return $this->canUpdateFlightPlans()
            || ($this->is_active && $this->role === UserRole::Dispatch);
    }

    public function createsFlightPlanRevisionsOnly(): bool
    {
        return $this->is_active && $this->role === UserRole::Pilot;
    }

    private function isRpusStation(): bool
    {
        return strtoupper(trim((string) $this->station)) === 'RPUS';
    }

    public function flights(): HasMany
    {
        return $this->hasMany(Flight::class);
    }
}
