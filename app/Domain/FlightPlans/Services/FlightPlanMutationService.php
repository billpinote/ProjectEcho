<?php

namespace App\Domain\FlightPlans\Services;

use App\Domain\FlightPlans\Enums\FlightPlanStatus;
use App\Models\Flight;
use App\Models\FlightPlanEvent;
use App\Models\User;
use App\Domain\FlightPlans\Rules\UtcFourDigitTime;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FlightPlanMutationService
{
    public function recordSubmission(Flight $flight, ?User $actor = null): void
    {
        FlightPlanEvent::record($flight, FlightPlanEvent::TYPE_CREATED, $actor, null, [
            'status' => $flight->status?->value ?? $flight->status,
            'aircraft_identification' => $flight->aircraft_identification,
        ]);

        FlightPlanEvent::record($flight, $flight->requiresPicAuthorization()
            ? FlightPlanEvent::TYPE_SUBMITTED_FOR_PIC_AUTHORIZATION
            : FlightPlanEvent::TYPE_SUBMITTED_TO_ATC, $actor, null, [
                'status' => $flight->status?->value ?? $flight->status,
                'proposed_time' => $flight->proposed_time,
                'date_of_flight' => $flight->date_of_flight,
                'aircraft_identification' => $flight->aircraft_identification,
                'filed_by_user_id' => $flight->filed_by_user_id,
            ]);
    }

    public function delay(Flight $flight, User $actor, string $newProposedTime): Flight
    {
        if (! $actor->can('delay', $flight)) {
            throw new AuthorizationException;
        }

        if (! UtcFourDigitTime::isValid($newProposedTime)) {
            throw ValidationException::withMessages([
                'new_proposed_time' => UtcFourDigitTime::message('proposed time'),
            ]);
        }

        $normalizedTime = UtcFourDigitTime::normalizeForStorage($newProposedTime);

        return DB::transaction(function () use ($actor, $flight, $normalizedTime): Flight {
            $flight->refresh();

            if (! $flight->canBeDelayedByPilot()) {
                throw ValidationException::withMessages([
                    'new_proposed_time' => 'This flight plan can no longer be delayed.',
                ]);
            }

            $oldTime = $flight->currentEobt();

            if ($normalizedTime === $oldTime) {
                throw ValidationException::withMessages([
                    'new_proposed_time' => 'The new proposed time must be different from the current operational EOBT.',
                ]);
            }

            $flight->forceFill([
                'revised_eobt' => $normalizedTime,
            ])->save();

            FlightPlanEvent::record($flight, FlightPlanEvent::TYPE_DELAYED, $actor, ['eobt' => $oldTime], [
                    'eobt' => $flight->revised_eobt,
                    'original_eobt' => $flight->proposed_time,
                ]);

            return $flight;
        });
    }

    public function cancel(Flight $flight, User $actor, ?string $reason = null): Flight
    {
        if (! $actor->can('cancel', $flight)) {
            throw new AuthorizationException;
        }

        $reason = filled($reason) ? trim((string) $reason) : null;

        if ($reason !== null && mb_strlen($reason) > 255) {
            throw ValidationException::withMessages([
                'reason' => 'The cancellation reason must not be greater than 255 characters.',
            ]);
        }

        return DB::transaction(function () use ($actor, $flight, $reason): Flight {
            $flight->refresh();

            if (! $flight->canBeCancelledByPilot()) {
                throw ValidationException::withMessages([
                    'reason' => 'This flight plan can no longer be cancelled.',
                ]);
            }

            $oldStatus = $flight->status?->value ?? $flight->status;

            $flight->forceFill([
                'status' => FlightPlanStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by_user_id' => $actor->getKey(),
            ])->save();

            FlightPlanEvent::record($flight, FlightPlanEvent::TYPE_CANCELLED, $actor, ['status' => $oldStatus], ['status' => FlightPlanStatus::Cancelled->value], $reason);

            return $flight;
        });
    }
}
