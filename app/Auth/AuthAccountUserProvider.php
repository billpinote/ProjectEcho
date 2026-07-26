<?php

namespace App\Auth;

use App\Models\AuthAccount;
use App\Models\User;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Hashing\Hasher as HasherContract;

class AuthAccountUserProvider implements UserProvider
{
    protected HasherContract $hasher;

    protected string $model;

    protected EloquentUserProvider $delegate;

    public function __construct(HasherContract $hasher, string $model)
    {
        $this->hasher = $hasher;
        $this->model = $model;
        $this->delegate = new EloquentUserProvider($hasher, $model);
    }

    public function retrieveById($identifier): ?Authenticatable
    {
        return $this->delegate->retrieveById($identifier);
    }

    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        return $this->delegate->retrieveByToken($identifier, $token);
    }

    public function updateRememberToken(Authenticatable $user, $token): void
    {
        $this->delegate->updateRememberToken($user, $token);
    }

    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        if (empty($credentials)) {
            return null;
        }

        $password = $credentials['password'] ?? null;
        $email = $credentials['email'] ?? $credentials['identifier'] ?? null;
        $identifier = $credentials['identifier'] ?? null;

        if ($password !== null && ($email !== null || $identifier !== null)) {
            $query = AuthAccount::query()->where('provider', 'password');

            if ($email !== null) {
                $query->where(function ($query) use ($email) {
                    $query->where('email', $email)
                        ->orWhere('identifier', $email);
                });
            } elseif ($identifier !== null) {
                $query->where(function ($query) use ($identifier) {
                    $query->where('identifier', $identifier)
                        ->orWhere('email', $identifier);
                });
            }

            $authAccount = $query->first();

            if ($authAccount !== null) {
                return $authAccount->user;
            }
        }

        if ($email !== null) {
            return User::where('email', $email)->first();
        }

        return null;
    }

    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        $plain = $credentials['password'] ?? '';

        $account = $user->authAccounts()
            ->where('provider', 'password')
            ->when($credentials['email'] ?? null, function ($query, $email) {
                $query->where(function ($query) use ($email) {
                    $query->where('email', $email)
                        ->orWhere('identifier', $email);
                });
            })
            ->first();

        if ($account !== null && ! empty($account->password_hash)) {
            return $this->hasher->check($plain, $account->password_hash);
        }

        return $this->hasher->check($plain, $user->getAuthPassword());
    }

    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): Authenticatable
    {
        $rehased = $this->delegate->rehashPasswordIfRequired($user, $credentials, $force);

        return $rehased ?? $user;
    }
}
