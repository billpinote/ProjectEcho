<?php

namespace App\Domain\FlightPlans\Services;

use App\Models\Flight;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class AvsecOperationalFlightQuery
{
    public function __construct(private FlightWorkflowService $workflow) {}

    public function forOfficer(?User $officer): Builder
    {
        $query = Flight::query()->visibleTo($officer);
        if (! AvsecVerificationService::eligibleOfficer($officer)) {
            return $query->whereRaw('1 = 0');
        }

        $now = CarbonImmutable::now('UTC');
        $start = $now->subHours(24);
        $end = $now->addHours(24);

        // DOF and proposed_time are already UTC. Compare the stored date and clock
        // separately so the window works on both SQLite and MySQL without conversion.
        return $this->workflow->withStatusContext($query)
            ->whereNotNull('proposed_time')
            ->where(fn (Builder $query) => $query->whereDate('date_of_flight', '>', $start->toDateString())
                ->orWhere(fn (Builder $query) => $query->whereDate('date_of_flight', $start->toDateString())
                    ->whereTime('proposed_time', '>=', $start->format('H:i:s'))))
            ->where(fn (Builder $query) => $query->whereDate('date_of_flight', '<', $end->toDateString())
                ->orWhere(fn (Builder $query) => $query->whereDate('date_of_flight', $end->toDateString())
                    ->whereTime('proposed_time', '<=', $end->format('H:i:s'))));
    }
}
