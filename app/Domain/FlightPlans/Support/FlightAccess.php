<?php

namespace App\Domain\FlightPlans\Support;

use App\Domain\FlightPlans\Enums\FlightPlanStatus;
use App\Domain\FlightPlans\Services\AvsecVerificationService;
use App\Domain\FlightPlans\Services\FilingAuthorizationService;
use App\Domain\Users\Enums\UserRole;
use App\Models\Flight;
use App\Models\FlightManifest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class FlightAccess
{
    public static function canViewManifest(User $user, FlightManifest $manifest): bool
    {
        return self::canView($user, $manifest->flight)
            && (! $user->isPilot() || (int) $manifest->created_by_user_id === (int) $user->getKey());
    }

    public static function restrictQueryToVisibleManifestFlights(Builder $query, ?User $user): Builder
    {
        self::restrictQueryToVisibleFlights($query, $user);

        if ($user?->isPilot()) {
            $query->where(function (Builder $query) use ($user): void {
                $query->whereHas('manifest', fn (Builder $manifest): Builder => $manifest->where('created_by_user_id', $user->getKey()))
                    ->orWhere(function (Builder $flight) use ($user): void {
                        $flight->whereDoesntHave('manifest')
                            ->where(fn (Builder $creator): Builder => $creator
                                ->where('prepared_by_user_id', $user->getKey())
                                ->orWhere('filed_by_user_id', $user->getKey()));
                    });
            });
        }

        return $query;
    }

    public static function canView(User $user, Flight $flight): bool
    {
        if ($user->isAvsec() && (! AvsecVerificationService::eligibleOfficer($user)
            || ! AvsecVerificationService::requiredFor($flight))) {
            return false;
        }
        if ($user->role === UserRole::Atmo
            && ! (strtoupper(trim((string) $flight->departure_aerodrome)) === 'RPUS' && self::isEarlyStagePreview($flight))
            && ! app(FilingAuthorizationService::class)->isReadyForAtmo($flight)) {
            return false;
        }
        if (! $user->is_active || ! $user->canViewFlightPlans()) {
            return false;
        }

        if ($user->isPilot()) {
            return $flight->isPilotInvolved($user)
                || self::isPicAuthorizationDeclineActor($user, $flight)
                || self::canPreviewOperatorFlight($user, $flight);
        }

        if ($user->isDispatch()) {
            return self::operatorMatches($user, $flight);
        }

        if ($user->isOperatorStaff()) {
            return ($flight->prepared_by_user_id !== null
                && (int) $flight->prepared_by_user_id === (int) $user->getKey())
                || self::canPreviewOperatorFlight($user, $flight);
        }

        return true;
    }

    public static function canAccessPicAuthorization(User $user, Flight $flight): bool
    {
        if ($user->role === UserRole::Atmo) {
            return self::canView($user, $flight)
                && app(FilingAuthorizationService::class)->isReadyForAtmo($flight);
        }
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
        if (! in_array($flight->status, [FlightPlanStatus::Accepted, FlightPlanStatus::Active], true)
            || $flight->isPicAuthorizationDeclined()) {
            return false;
        }
        if ($user->role === UserRole::Atmo && ! self::canView($user, $flight)) {
            return false;
        }
        if (! $user->is_active) {
            return false;
        }

        if ($user->hasFullFlightAccess()) {
            return true;
        }

        return $user->isDispatch() && self::operatorMatches($user, $flight) && self::isCreator($user, $flight);
    }

    public static function isCreator(User $user, Flight $flight): bool
    {
        return ($flight->prepared_by_user_id !== null && (int) $flight->prepared_by_user_id === (int) $user->id)
            || ($flight->filed_by_user_id !== null && (int) $flight->filed_by_user_id === (int) $user->id);
    }

    public static function operatorMatches(User $user, Flight $flight): bool
    {
        return $user->operator_id !== null
            && $flight->operator_id !== null
            && (int) $user->operator_id === (int) $flight->operator_id;
    }

    private static function canPreviewOperatorFlight(User $user, Flight $flight): bool
    {
        return self::operatorMatches($user, $flight) && self::isEarlyStagePreview($flight);
    }

    private static function isEarlyStagePreview(Flight $flight): bool
    {
        return in_array($flight->status, [FlightPlanStatus::AwaitingPic, FlightPlanStatus::Pending], true)
            && ! $flight->isPicAuthorizationDeclined()
            && $flight->pic_authorization_archived_at === null
            && ! $flight->isPendingExpired();
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
        if ($user->isAvsec()) {
            return AvsecVerificationService::eligibleOfficer($user)
                ? $query->whereRaw('UPPER(TRIM(departure_aerodrome)) = ?', ['RPUS'])
                : $query->whereRaw('1 = 0');
        }
        if ($user->role === UserRole::Atmo) {
            $readyIds = app(FilingAuthorizationService::class)->readyIds($query);

            return $query->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query
                    ->whereRaw('UPPER(TRIM(departure_aerodrome)) = ?', ['RPUS'])
                    ->where(fn (Builder $query) => $query->where('status', FlightPlanStatus::AwaitingPic)->orWhere(fn (Builder $query) => $query->pendingActive()))
                    ->where(fn (Builder $query) => $query->whereNull('pic_authorization_status')->orWhere('pic_authorization_status', '!=', 'declined'))
                    ->whereNull('pic_authorization_archived_at'))
                ->orWhere(fn (Builder $query) => $query->whereKey($readyIds)));
        }

        if ($user->isPilot()) {
            return $query->where(function (Builder $query) use ($user): void {
                self::restrictQueryToPilotInvolvement($query, $user, includePicAuthorizationDeclineActor: true);
                self::includeOperatorPreviews($query, $user);
            });
        }

        if ($user->isDispatch()) {
            if ($user->operator_id === null) {
                return $query->whereRaw('1 = 0');
            }

            return $query->where('operator_id', $user->operator_id);
        }

        if ($user->isOperatorStaff()) {
            return $query->where(function (Builder $query) use ($user): void {
                $query->where('prepared_by_user_id', $user->getKey());
                self::includeOperatorPreviews($query, $user);
            });
        }

        return $query;
    }

    private static function includeOperatorPreviews(Builder $query, User $user): void
    {
        if ($user->operator_id === null) {
            return;
        }

        $query->orWhere(fn (Builder $query) => $query
            ->where('operator_id', $user->operator_id)
            ->whereIn('status', [FlightPlanStatus::AwaitingPic, FlightPlanStatus::Pending])
            ->where(fn (Builder $query) => $query->whereNull('pic_authorization_status')->orWhere('pic_authorization_status', '!=', 'declined'))
            ->whereNull('pic_authorization_archived_at')
            ->where(fn (Builder $query) => $query->where('status', FlightPlanStatus::AwaitingPic)->orWhere(fn (Builder $query) => $query->pendingActive())));
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
