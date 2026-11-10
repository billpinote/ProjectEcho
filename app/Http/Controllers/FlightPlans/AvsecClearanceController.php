<?php

namespace App\Http\Controllers\FlightPlans;

use App\Domain\FlightPlans\Services\AvsecVerificationService;
use App\Domain\FlightPlans\Services\FlightPlanQrPayloadService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AvsecClearanceRequest;
use App\Models\Flight;
use Illuminate\Http\RedirectResponse;

class AvsecClearanceController extends Controller
{
    public function __construct(private AvsecVerificationService $clearance, private FlightPlanQrPayloadService $payloads) {}

    public function store(AvsecClearanceRequest $request, Flight $flight): RedirectResponse
    {
        $data = $request->validated();
        $payload = $this->payloads->buildPayload($flight);
        if ($data['decision'] === 'grant') {
            $this->clearance->confirm($payload, $request->user(), true, $data['reference'] ?? null, $data['reviewed_fingerprint']);
        } else {
            $this->clearance->deny($payload, $request->user(), $data['reason'], $data['reference'] ?? null, $data['reviewed_fingerprint']);
        }

        return redirect()->route('flights.view', $flight)->with('status', $data['decision'] === 'grant' ? 'Clearance Granted' : 'Clearance Denied');
    }
}
