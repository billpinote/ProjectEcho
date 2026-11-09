<?php

namespace App\Domain\FlightPlans\Services;

use App\Domain\FlightPlans\Enums\FlightPlanStatus;
use App\Models\Flight;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AvsecDepartureQuery
{
    public function __construct(private FlightWorkflowService $workflow) {}

    public function forOfficer(?User $officer): Builder
    {
        $query = Flight::query()->whereRaw('UPPER(TRIM(departure_aerodrome)) = ?', ['RPUS'])
            ->whereNull('pic_authorization_archived_at')
            ->whereNotIn('status', [FlightPlanStatus::Rejected, FlightPlanStatus::Cancelled])
            ->whereDate('date_of_flight', '>=', now('Asia/Manila')->toDateString());
        if (! AvsecVerificationService::eligibleOfficer($officer)) {
            return $query->whereRaw('1 = 0');
        }

        return $this->workflow->withStatusContext($query)->whereDoesntHave('avsecVerification');
    }
}
