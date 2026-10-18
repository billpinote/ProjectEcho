<?php

namespace App\Policies;

use App\Domain\FlightPlans\Enums\FlightPlanStatus;
use App\Domain\FlightPlans\Support\FlightAccess;
use App\Domain\Users\Enums\UserRole;
use App\Models\Flight;
use App\Models\FlightManifest;
use App\Models\User;

class FlightManifestPolicy
{
    public function view(User $user, FlightManifest $manifest): bool
    {
        return FlightAccess::canView($user, $manifest->flight);
    }

    public function create(User $user, Flight $flight): bool
    {
        return in_array($user->role, [UserRole::Pilot, UserRole::Dispatch, UserRole::Admin, UserRole::Artisan], true)
            && $user->canCreateFlightPlans()
            && FlightAccess::canView($user, $flight)
            && ! $flight->hasBeenAccepted()
            && ! $flight->isPicAuthorizationDeclined()
            && ! in_array($flight->status, [FlightPlanStatus::Cancelled, FlightPlanStatus::Rejected], true)
            && ! $flight->isPendingExpired();
    }

    public function update(User $user, FlightManifest $manifest): bool
    {
        return $this->create($user, $manifest->flight);
    }

    public function delete(User $user, FlightManifest $manifest): bool
    {
        return $this->update($user, $manifest);
    }
}
