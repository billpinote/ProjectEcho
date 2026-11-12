<?php

namespace App\Domain\FlightPlans\Services;

use App\Domain\FlightPlans\Enums\AvsecClearanceState;
use App\Domain\FlightPlans\Enums\FlightPlanStatus;
use App\Domain\FlightPlans\Support\FlightStatusDisplay;
use App\Models\Flight;
use App\Models\FlightPlanEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class FlightWorkflowService
{
    private const RELATIONS = ['manifest.persons', 'filingAuthorizations.pilotSignature', 'avsecVerification', 'avsecDenials'];

    public function __construct(private FilingAuthorizationService $authorizations, private FlightManifestService $manifests, private AvsecVerificationService $avsec) {}

    public function withStatusContext(Builder $query): Builder
    {
        return $query->with(self::RELATIONS)->withExists([
            'events as atc_acceptance_exists' => fn (Builder $query) => $query->where('event_type', FlightPlanEvent::TYPE_ATC_ACCEPTED),
        ]);
    }

    public function pendingStage(Flight $flight): string
    {
        if (! $flight->exists || ! in_array($flight->status, [FlightPlanStatus::AwaitingPic, FlightPlanStatus::Pending], true) || $flight->isPendingExpired()
            || $flight->isPicAuthorizationDeclined() || $flight->pic_authorization_archived_at !== null) {
            return FlightStatusDisplay::PENDING;
        }
        $flight->loadMissing(self::RELATIONS);
        if (! array_key_exists('atc_acceptance_exists', $flight->getAttributes())) {
            $flight->loadExists(['events as atc_acceptance_exists' => fn (Builder $query) => $query->where('event_type', FlightPlanEvent::TYPE_ATC_ACCEPTED)]);
        }
        if ($flight->accepted_by_user_id !== null || $flight->atc_acceptance_exists) {
            return FlightStatusDisplay::PENDING;
        }
        try {
            $this->manifests->assertComplete($flight, loaded: true);
        } catch (ValidationException) {
            return FlightStatusDisplay::MANIFEST_REQUIRED;
        }
        if ($flight->status === FlightPlanStatus::AwaitingPic || ! $this->authorizations->isReadyForAtmo($flight, loaded: true)) {
            return FlightStatusDisplay::AWAITING_PIC;
        }
        if (! AvsecVerificationService::requiredFor($flight)) {
            return FlightStatusDisplay::PENDING_ATMO;
        }

        return match ($this->avsec->currentState($flight, loaded: true)) {
            AvsecClearanceState::Granted, AvsecClearanceState::Denied => FlightStatusDisplay::PENDING_ATMO,
            AvsecClearanceState::Awaiting => $flight->avsecVerification === null
                ? FlightStatusDisplay::AWAITING_AVSEC : FlightStatusDisplay::PENDING,
        };
    }

    public function queue(Builder $query, string $stage): Builder
    {
        $ids = $this->withStatusContext(clone $query)->pendingActive()->get()
            ->filter(fn (Flight $flight): bool => $this->pendingStage($flight) === $stage)->modelKeys();

        return $this->withStatusContext($query)->whereKey($ids);
    }
}
