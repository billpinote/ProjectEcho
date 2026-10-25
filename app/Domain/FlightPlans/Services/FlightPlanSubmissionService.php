<?php

namespace App\Domain\FlightPlans\Services;

use App\Domain\FlightPlans\Support\AuthenticatedOperatorFlightData;
use App\Domain\FlightPlans\Support\FlightPlanPreparerContext;
use App\Models\Flight;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FlightPlanSubmissionService
{
    public function __construct(
        private FlightPlanMutationService $mutations,
        private FlightPlanPdfService $pdfs,
    ) {}

    /** @param array<string, mixed> $flightData
     * @return array{flight: Flight, pdf_path: string}
     */
    public function submit(array $flightData, User $user): array
    {
        $preparerContext = FlightPlanPreparerContext::for($user, $flightData);
        $flightData = $preparerContext->applyToFlightData($flightData);

        if ($preparerContext->preparerActsAsPic()) {
            $flightData['user_id'] = $user->id;
            $flightData['pilot_id'] = $flightData['pilot_id'] ?? $user->id;
            $flightData['pilot_in_command_user_id'] = $flightData['pilot_in_command_user_id'] ?? $user->id;
        }

        $flightData = AuthenticatedOperatorFlightData::apply($flightData, $user);

        $flight = DB::transaction(function () use ($flightData, $user) {
            $flight = Flight::create($flightData);

            $this->mutations->recordSubmission($flight, $user);

            return $flight;
        });

        $flight->refresh();

        $storedPdfPath = $this->pdfs->regenerate($flight);

        return ['flight' => $flight, 'pdf_path' => $storedPdfPath];

    }
}
