<?php

namespace App\Domain\FlightPlans\Services;

use App\Auth\AuthenticationEvidence;
use App\Domain\FlightPlans\Enums\FlightPlanStatus;
use App\Domain\FlightPlans\Support\AuthenticatedOperatorFlightData;
use App\Domain\FlightPlans\Support\FilingDocumentRegistry;
use App\Domain\FlightPlans\Support\FlightPlanPreparerContext;
use App\Domain\FlightPlans\Support\PilotFlightPlanCredentials;
use App\Models\Flight;
use App\Models\FlightPlanEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FlightPlanSubmissionService
{
    public function __construct(
        private FlightPlanMutationService $mutations,
        private FlightPlanPdfService $pdfs,
    ) {}

    /** @param array<string, mixed> $data */
    public function submitSelfPrepared(array $data, User $pic, AuthenticationEvidence $evidence, string $submissionId): Flight
    {
        $evidence->assertActor($pic);

        return DB::transaction(function () use ($data, $pic, $submissionId): Flight {
            // Serialize retries from the same pilot, including requests with stale Livewire state.
            $pic = User::query()->lockForUpdate()->findOrFail($pic->id);
            $profile = $pic->pilotProfile()->lockForUpdate()->first();
            if ($profile !== null) {
                $profile->setRelation('qualifications', $profile->qualifications()->lockForUpdate()->get());
            }
            $pic->setRelation('pilotProfile', $profile);
            $pic->setRelation('operator', $pic->operator()->lockForUpdate()->first());
            abort_unless($pic->is_active && $pic->isPilot() && $pic->can('create', Flight::class)
                && FlightPlanPreparerContext::for($pic, $data)->preparerActsAsPic(), 403);
            $fingerprint = FilingDocumentRegistry::fingerprint($data);
            $previous = FlightPlanEvent::query()->where('actor_user_id', $pic->id)
                ->where('event_type', FlightPlanEvent::TYPE_CREATED)
                ->where('new_values->self_submission_id', $submissionId)->lockForUpdate()->first();
            if ($previous !== null) {
                if (! hash_equals($previous->new_values['submission_fingerprint'], $fingerprint)) {
                    throw ValidationException::withMessages(['signature' => 'This submission was already completed. Start a new filing for different flight information.']);
                }

                return $previous->flight()->lockForUpdate()->firstOrFail();
            }
            $messages = PilotFlightPlanCredentials::validationMessages($pic, $data['date_of_flight'] ?? null, lockForUpdate: true);
            if ($messages !== []) {
                throw ValidationException::withMessages($messages);
            }
            $trustedData = FlightPlanPreparerContext::for($pic, $data)->applyToFlightData($data);
            $trustedData = AuthenticatedOperatorFlightData::apply(PilotFlightPlanCredentials::applySnapshot($trustedData, $pic, lockForUpdate: true), $pic);
            foreach (['pilot_in_command', 'pilot_license_no', 'pilot_ratings', 'license_expiry_date', 'operator_id', 'other_info_opr'] as $field) {
                if (array_key_exists($field, $data) && $data[$field] !== ($trustedData[$field] ?? null)) {
                    throw ValidationException::withMessages(['signature' => 'Your verified profile or operator changed. Review and confirm the filing again.']);
                }
            }
            $data = $trustedData;
            $data['user_id'] = $pic->id;
            $data['pilot_id'] = $pic->id;
            $data['pilot_in_command_user_id'] = $pic->id;
            $data['status'] = FlightPlanStatus::AwaitingPic;
            $flight = Flight::create($data)->refresh();
            $this->mutations->recordSubmission($flight, $pic, [
                'self_submission_id' => $submissionId, 'submission_fingerprint' => $fingerprint,
            ]);

            return $flight;
        });
    }

    /** @param array<string, mixed> $flightData
     * @return array{flight: Flight, pdf_path: string}
     */
    public function submit(array $flightData, User $user): array
    {
        $preparerContext = FlightPlanPreparerContext::for($user, $flightData);
        if ($preparerContext->preparerActsAsPic()) {
            throw ValidationException::withMessages(['signature' => 'Create your flight plan, complete its manifest, then authorize and sign the complete filing.']);
        }
        $flightData = $preparerContext->applyToFlightData($flightData);

        $flightData = AuthenticatedOperatorFlightData::apply($flightData, $user);

        $flight = DB::transaction(function () use ($flightData, $user) {
            $flight = Flight::create($flightData)->refresh();

            $this->mutations->recordSubmission($flight, $user);

            return $flight;
        });

        $flight->refresh();

        $storedPdfPath = $this->pdfs->regenerate($flight);

        return ['flight' => $flight, 'pdf_path' => $storedPdfPath];

    }
}
