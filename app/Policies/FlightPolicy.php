<?php

namespace App\Policies;

use App\Models\Flight;
use App\Models\User;
use Illuminate\Auth\Access\Response;

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
        // Controllers, Dispatch, AVSEC, Admin, etc.
        if ($user->hasFullFlightAccess()) {
            return true;
        }

        // Pilots may only view their own flights.
        return $flight->user_id === $user->id;
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
        // Controllers/Admin can always update.
        if ($user->hasFullFlightAccess()) {
            return true;
        }

        // Pilot may only edit their own flight.
        if ($flight->user_id !== $user->id) {
            return false;
        }

        // Future:
        // Only allow editing while Draft/Pending.
        return true;
    }

    /**
     * Determine whether the user can delete the flight.
     */
    public function delete(User $user, Flight $flight): bool
    {
        if ($user->hasFullFlightAccess()) {
            return true;
        }

        return $flight->user_id === $user->id;
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
}
