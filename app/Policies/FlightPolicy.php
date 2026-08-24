<?php

namespace App\Policies;

use App\Domain\FlightPlans\Support\FlightAccess;
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
        return FlightAccess::canView($user, $flight);
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
        return $user->is_active
            && $user->hasFullFlightAccess()
            && $flight->pic_authorization_status !== 'declined';
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
        return $user->canReviewFlightPlans()
            && $flight->canSubmitToAtc()
            && FlightAccess::canView($user, $flight);
    }

    /**
     * Determine whether the user may reject a flight plan.
     */
    public function reject(User $user, Flight $flight): bool
    {
        return $user->canReviewFlightPlans()
            && $flight->canSubmitToAtc()
            && FlightAccess::canView($user, $flight);
    }

    public function updateStartUpTime(User $user, Flight $flight): bool
    {
        return $user->canUpdateFlightStartUpTime() && FlightAccess::canOperationallyUpdate($user, $flight);
    }

    public function updateBlockOffTime(User $user, Flight $flight): bool
    {
        return $user->canUpdateFlightBlockOffTime() && FlightAccess::canOperationallyUpdate($user, $flight);
    }

    public function updateAirborneTime(User $user, Flight $flight): bool
    {
        return $user->canUpdateFlightPlans() && FlightAccess::canOperationallyUpdate($user, $flight);
    }

    public function updateTouchdownTime(User $user, Flight $flight): bool
    {
        return $user->canUpdateFlightPlans() && FlightAccess::canOperationallyUpdate($user, $flight);
    }

    public function updateShutdownTime(User $user, Flight $flight): bool
    {
        return $user->canUpdateFlightShutdownTime() && FlightAccess::canOperationallyUpdate($user, $flight);
    }

    public function delay(User $user, Flight $flight): bool
    {
        return $user->isPilot()
            && $flight->isPilotInCommand($user)
            && $flight->canBeDelayedByPilot();
    }

    public function cancel(User $user, Flight $flight): bool
    {
        return $user->isPilot()
            && $flight->isPilotInCommand($user)
            && $flight->canBeCancelledByPilot();
    }
}
