<?php

namespace App\Models;

use App\Enums\UserRole;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'username',
        'employee_id',
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

    public function acceptedFlights()
    {
        return $this->hasMany(Flight::class, 'accepted_by_user_id');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin'
            && $this->is_active
            && $this->canAccessFlightPanel();
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
}
