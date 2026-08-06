<?php

namespace App\Filament\Pages\Concerns;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Facades\Filament;

trait ResolvesPilotPanelProfileUser
{
    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User
            && $user->is_active
            && ($user->isPilot() || $user->role === UserRole::Artisan);
    }

    protected function getProfileUser(): User
    {
        /** @var User|null $user */
        $user = Filament::auth()->user();

        abort_unless($user instanceof User, 403);
        abort_unless(static::canAccess(), 403);

        return $user->loadMissing('pilotProfile');
    }
}
