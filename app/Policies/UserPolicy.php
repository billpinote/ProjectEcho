<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, User $target): bool
    {
        return $user->isPilot() && $user->id === $target->id;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, User $target): bool
    {
        return $user->isPilot() && $user->id === $target->id;
    }

    public function delete(User $user, User $target): bool
    {
        return false;
    }
}
