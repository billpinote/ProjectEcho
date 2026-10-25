<?php

namespace App\Domain\FlightPlans\Services;

use App\Models\Flight;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class FlightReportQueryService
{
    public function abbreviated(?User $user): Builder
    {
        $query = Flight::query()->visibleTo($user)->where('received_facility', 'RPUS');

        return Schema::hasColumn((new Flight)->getTable(), 'status')
            ? $query->accepted()
            : $query->whereNotNull('accepted_by_user_id');
    }

    public function postOperations(?User $user): Builder
    {
        return $this->abbreviated($user)->whereNotNull('time_airborne');
    }
}
