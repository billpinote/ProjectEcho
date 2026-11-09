<?php

namespace App\Domain\FlightPlans\Services;

use App\Domain\FlightPlans\Enums\FlightPlanStatus;
use App\Domain\FlightPlans\Support\FlightStatusDisplay;
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
            ->where(fn (Builder $query) => $query->where('status', '!=', FlightPlanStatus::Pending)
                ->orWhereDate('date_of_flight', '>=', now('Asia/Manila')->toDateString()));
        if (! AvsecVerificationService::eligibleOfficer($officer)) {
            return $query->whereRaw('1 = 0');
        }

        $ids = [];
        $this->workflow->withStatusContext((clone $query)->pendingActive()->whereDoesntHave('avsecVerification'))
            ->chunkById(100, function ($flights) use (&$ids): void {
                foreach ($flights as $flight) {
                    if ($this->workflow->pendingStage($flight) === FlightStatusDisplay::AWAITING_AVSEC) {
                        $ids[] = $flight->id;
                    }
                }
            });

        return $query->whereKey($ids)->whereDoesntHave('avsecVerification');
    }
}
