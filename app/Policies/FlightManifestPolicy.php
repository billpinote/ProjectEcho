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
        return FlightAccess::canViewManifest($user, $manifest);
    }

    public function create(User $user, Flight $flight): bool
    {
        return in_array($user->role, [UserRole::Pilot, UserRole::Dispatch, UserRole::Admin, UserRole::Artisan], true)
            && $user->canCreateFlightPlans()
            && FlightAccess::canView($user, $flight)
            && (! $user->isDispatch() || FlightAccess::isCreator($user, $flight))
            && (! $user->isPilot() || FlightAccess::isCreator($user, $flight))
            && ! $flight->hasBeenAccepted()
            && ! $flight->isAvsecFrozen()
            && ! $flight->isPicAuthorizationDeclined()
            && ! in_array($flight->status, [FlightPlanStatus::Cancelled, FlightPlanStatus::Rejected], true)
            && ! $flight->isPendingExpired();
    }

    public function update(User $user, FlightManifest $manifest): bool
    {
        return $this->view($user, $manifest) && $this->create($user, $manifest->flight);
    }

    public function delete(User $user, FlightManifest $manifest): bool
    {
        return $this->update($user, $manifest);
    }
}
