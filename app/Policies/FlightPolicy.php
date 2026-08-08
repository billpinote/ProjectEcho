<?php

namespace App\Policies;

use App\Models\Flight;
use App\Models\User;

class FlightPolicy
{
    /**
     * Determine whether the user can view any flights.
     */
    public function viewAny(User $user): bool
    {
        return $user->canViewFlightPlans();
    }

    /**
     * Determine whether the user can view the flight.
     */
    public function view(User $user, Flight $flight): bool
    {
        if (! $user->isPilot() && $user->canViewFlightPlans()) {
            return true;
        }

        return $flight->isOwnedBy($user);
    }

    /**
     * Determine whether the user can create flights.
     */
    public function create(User $user): bool
    {
        return $user->canCreateFlightPlans();
    }

    /**
     * Determine whether the user can update the flight.
     */
    public function update(User $user, Flight $flight): bool
    {
        if ($user->hasFullFlightAccess()) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the flight.
     */
    public function delete(User $user, Flight $flight): bool
    {
        return $user->hasFullFlightAccess();
    }

    /**
     * Determine whether the user can restore the flight.
     */
    public function restore(User $user, Flight $flight): bool
    {
        return $user->hasFullFlightAccess();
    }

    /**
     * Determine whether the user can permanently delete the flight.
     */
    public function forceDelete(User $user, Flight $flight): bool
    {
        return $user->hasFullFlightAccess();
    }

    /**
     * Determine whether the user may accept a flight plan.
     */
    public function accept(User $user, Flight $flight): bool
    {
        return $user->canReviewFlightPlans();
    }

    /**
     * Determine whether the user may reject a flight plan.
     */
    public function reject(User $user, Flight $flight): bool
    {
        return $user->canReviewFlightPlans();
    }

    public function delay(User $user, Flight $flight): bool
    {
        return $user->isPilot()
            && $flight->isOwnedBy($user)
            && $flight->canBeDelayedByPilot();
    }

    public function cancel(User $user, Flight $flight): bool
    {
        return $user->isPilot()
            && $flight->isOwnedBy($user)
            && $flight->canBeCancelledByPilot();
    }
}
