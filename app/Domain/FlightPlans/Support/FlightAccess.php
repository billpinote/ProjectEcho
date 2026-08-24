<?php

namespace App\Domain\FlightPlans\Support;

use App\Models\Flight;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class FlightAccess
{
    public static function canView(User $user, Flight $flight): bool
    {
        if (! $user->is_active || ! $user->canViewFlightPlans()) {
            return false;
        }

        if ($user->isPilot()) {
            return $flight->isPilotInvolved($user)
                || self::isPicAuthorizationDeclineActor($user, $flight);
        }

        if ($user->isDispatch()) {
            return self::operatorMatches($user, $flight);
        }

        if ($user->isOperatorStaff()) {
            return $flight->prepared_by_user_id !== null
                && (int) $flight->prepared_by_user_id === (int) $user->getKey();
        }

        return true;
    }

    public static function canAccessPicAuthorization(User $user, Flight $flight): bool
    {
        if (! $user->is_active || ! $user->canViewFlightPlans()) {
            return false;
        }

        if ($user->isPilot() || $user->isDispatch()) {
            return self::operatorMatches($user, $flight);
        }

        if ($user->isOperatorStaff()) {
            return $flight->prepared_by_user_id !== null
                && (int) $flight->prepared_by_user_id === (int) $user->getKey();
        }

        return $user->hasFullFlightAccess();
    }

    public static function canOperationallyUpdate(User $user, Flight $flight): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->hasFullFlightAccess()) {
            return true;
        }

        return $user->isDispatch() && self::operatorMatches($user, $flight);
    }

    public static function operatorMatches(User $user, Flight $flight): bool
    {
        return $user->operator_id !== null
            && $flight->operator_id !== null
            && (int) $user->operator_id === (int) $flight->operator_id;
    }

    public static function isPicAuthorizationDeclineActor(User $user, Flight $flight): bool
    {
        return $flight->isPicAuthorizationDeclined()
            && $flight->pic_authorization_declined_by_user_id !== null
            && (int) $flight->pic_authorization_declined_by_user_id === (int) $user->getKey();
    }

    public static function restrictQueryToVisibleFlights(Builder $query, ?User $user): Builder
    {
        if ($user === null || ! $user->is_active || ! $user->canViewFlightPlans()) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isPilot()) {
            return self::restrictQueryToPilotInvolvement(
                $query,
                $user,
                includePicAuthorizationDeclineActor: true,
            );
        }

        if ($user->isDispatch()) {
            if ($user->operator_id === null) {
                return $query->whereRaw('1 = 0');
            }

            return $query->where('operator_id', $user->operator_id);
        }

        if ($user->isOperatorStaff()) {
            return $query->where('prepared_by_user_id', $user->getKey());
        }

        return $query;
    }

    public static function restrictQueryToPilotInvolvement(Builder $query, ?User $user, bool $includePicAuthorizationDeclineActor = false): Builder
    {
        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        $userId = $user->getKey();

        return $query->where(function (Builder $query) use ($userId, $includePicAuthorizationDeclineActor): void {
            $query
                ->where('filed_by_user_id', $userId)
                ->orWhere('prepared_by_user_id', $userId)
                ->orWhere('pilot_in_command_user_id', $userId)
                ->orWhere('pilot_id', $userId);

            if ($includePicAuthorizationDeclineActor) {
                $query->orWhere('pic_authorization_declined_by_user_id', $userId);
            }
        });
    }
}
