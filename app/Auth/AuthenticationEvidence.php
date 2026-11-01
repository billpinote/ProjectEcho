<?php

namespace App\Auth;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\SessionGuard;

final readonly class AuthenticationEvidence
{
    private function __construct(private int $userId, private string $method) {}

    public static function fromSession(SessionGuard $guard): self
    {
        $user = $guard->user();

        if (! $guard->getProvider() instanceof AuthAccountUserProvider
            || ! $user instanceof User || ! $user->exists || ! $user->is_active) {
            throw new AuthorizationException('An active authenticated password session is required.');
        }

        return new self((int) $user->getKey(), 'password_session');
    }

    public function assertActor(User $actor): void
    {
        if (! $actor->is_active || (int) $actor->getKey() !== $this->userId) {
            throw new AuthorizationException('Authentication evidence does not belong to this actor.');
        }
    }

    /** @return array{user_id: int, method: string, freshly_verified: bool} */
    public function auditValues(): array
    {
        return ['user_id' => $this->userId, 'method' => $this->method, 'freshly_verified' => false];
    }
}
