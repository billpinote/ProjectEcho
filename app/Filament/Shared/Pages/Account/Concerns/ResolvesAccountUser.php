<?php

namespace App\Filament\Shared\Pages\Account\Concerns;

use App\Models\User;
use Filament\Facades\Filament;

trait ResolvesAccountUser
{
    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User
            && $user->canAccessPanel(Filament::getPanel(static::$accountPanel));
    }

    protected function getProfileUser(): User
    {
        abort_unless(static::canAccess(), 403);

        return Filament::auth()->user();
    }
}
