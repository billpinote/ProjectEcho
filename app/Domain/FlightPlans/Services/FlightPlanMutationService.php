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
        FlightPlanEvent::create([
            'flight_id' => $flight->getKey(),
            'actor_user_id' => $actor?->getKey(),
            'event_type' => FlightPlanEvent::TYPE_SUBMITTED,
            'old_values' => null,
            'new_values' => [
                'status' => $flight->status?->value ?? $flight->status,
                'proposed_time' => $flight->proposed_time,
                'date_of_flight' => $flight->date_of_flight,
                'aircraft_identification' => $flight->aircraft_identification,
                'filed_by_user_id' => $flight->filed_by_user_id,
            ],
            'created_at' => now(),
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

        if ($normalizedTime === $flight->proposed_time) {
            throw ValidationException::withMessages([
                'new_proposed_time' => 'The new proposed time must be different from the current proposed time.',
            ]);
        }

        return DB::transaction(function () use ($actor, $flight, $normalizedTime): Flight {
            $flight->refresh();

            if (! $flight->canBeDelayedByPilot()) {
                throw ValidationException::withMessages([
                    'new_proposed_time' => 'This flight plan can no longer be delayed.',
                ]);
            }

            $oldTime = $flight->proposed_time;

            $flight->forceFill([
                'proposed_time' => $normalizedTime,
            ])->save();

            FlightPlanEvent::create([
                'flight_id' => $flight->getKey(),
                'actor_user_id' => $actor->getKey(),
                'event_type' => FlightPlanEvent::TYPE_DELAYED,
                'old_values' => ['proposed_time' => $oldTime],
                'new_values' => ['proposed_time' => $flight->proposed_time],
                'created_at' => now(),
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

            FlightPlanEvent::create([
                'flight_id' => $flight->getKey(),
                'actor_user_id' => $actor->getKey(),
                'event_type' => FlightPlanEvent::TYPE_CANCELLED,
                'old_values' => ['status' => $oldStatus],
                'new_values' => ['status' => FlightPlanStatus::Cancelled->value],
                'reason' => $reason,
                'created_at' => now(),
            ]);

            return $flight;
        });
    }
}
