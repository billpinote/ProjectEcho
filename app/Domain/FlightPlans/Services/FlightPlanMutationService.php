<?php

namespace App\Domain\FlightPlans\Services;

use App\Domain\FlightPlans\Enums\FlightPlanStatus;
use App\Domain\FlightPlans\Rules\UtcFourDigitTime;
use App\Domain\FlightPlans\Support\FilingDocumentRegistry;
use App\Domain\FlightPlans\Support\FlightAccess;
use App\Models\Flight;
use App\Models\FlightPlanEvent;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FlightPlanMutationService
{
    public function reject(Flight $flight, User $actor, string $wiresign, string $reason): void
    {
        DB::transaction(function () use ($flight, $actor, $wiresign, $reason): void {
            $flight = Flight::query()->lockForUpdate()->findOrFail($flight->id);
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            if (! $actor->can('reject', $flight)) {
                throw new AuthorizationException;
            }
            $flight->forceFill([
                'status' => FlightPlanStatus::Rejected,
                'accepted_by_user_id' => null,
                'accepted_by_wiresign' => null,
                'rejected_by_wiresign' => $wiresign,
                'rejection_reason' => trim($reason),
                'received_by' => null,
                'received_date' => null,
                'received_time' => null,
                'received_facility' => null,
            ])->save();

            FlightPlanEvent::record($flight, FlightPlanEvent::TYPE_ATC_REJECTED, $actor, null, null, $flight->rejection_reason);
        });
    }

    public function accept(Flight $flight, User $actor, string $wiresign): Flight
    {
        return DB::transaction(function () use ($flight, $actor, $wiresign): Flight {
            $flight = Flight::query()->lockForUpdate()->findOrFail($flight->getKey());
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            if (! $actor->canReviewFlightPlans() || ! FlightAccess::canView($actor, $flight)) {
                throw new AuthorizationException;
            }
            if (AvsecVerificationService::requiredFor($flight) && ! app(AvsecVerificationService::class)->validFor($flight)) {
                throw ValidationException::withMessages(['avsec' => 'RPUS departures require current AVSEC confirmation before ATMO acceptance.']);
            }
            if (! $actor->can('accept', $flight)) {
                throw new AuthorizationException;
            }
            if (! $flight->canSubmitToAtc()) {
                throw ValidationException::withMessages(['flight' => 'Both current flight documents must have valid PIC signatures before ATMO acceptance.']);
            }
            if ($flight->isPendingExpired()) {
                throw ValidationException::withMessages(['flight' => 'This flight plan has expired.']);
            }
            app(FlightManifestService::class)->assertComplete($flight);
            if (! $flight->isAvsecFrozen()) {
                app(ManifestPdfService::class)->snapshot($flight);
            }
            $nowUtc = now('UTC');
            $flight->forceFill([
                'status' => FlightPlanStatus::Accepted,
                'accepted_by_user_id' => $actor->getKey(),
                'accepted_by_wiresign' => $wiresign,
                'rejected_by_wiresign' => null,
                'rejection_reason' => null,
                'received_by' => $wiresign,
                'received_date' => $nowUtc->toDateString(),
                'received_time' => $nowUtc->format('H:i'),
                'received_facility' => (string) $actor->station,
            ])->save();
            FlightPlanEvent::record($flight, FlightPlanEvent::TYPE_ATC_ACCEPTED, $actor, null, [
                'accepted_by_wiresign' => $wiresign,
                'received_facility' => $flight->received_facility,
            ]);

            return $flight;
        });
    }

    public function recordSubmission(Flight $flight, ?User $actor = null, array $submissionMetadata = []): void
    {
        if ($flight->events()->where('event_type', FlightPlanEvent::TYPE_CREATED)->exists()) {
            return;
        }

        FlightPlanEvent::record($flight, FlightPlanEvent::TYPE_CREATED, $actor, null, [
            ...$flight->only(array_diff(app(FilingDocumentRegistry::class)->materialFlightFields(), ['filed_by_signature'])),
            'status' => $flight->status?->value,
            'revision_number' => $flight->revision_number ?? 1,
            'revision_of_id' => $flight->revision_of_id,
            ...$submissionMetadata,
        ]);

        FlightPlanEvent::record($flight, ($flight->status === FlightPlanStatus::AwaitingPic || $flight->requiresPicAuthorization())
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
            $flight = Flight::query()->lockForUpdate()->findOrFail($flight->id);
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            if (! $actor->can('delay', $flight)) {
                throw new AuthorizationException;
            }

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
            $flight = Flight::query()->lockForUpdate()->findOrFail($flight->id);
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            if (! $actor->can('cancel', $flight)) {
                throw new AuthorizationException;
            }

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
