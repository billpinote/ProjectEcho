<?php

namespace App\Http\Controllers\FlightPlans;

use App\Domain\FlightPlans\Services\FlightPlanQrRenderer;
use App\Domain\FlightPlans\Support\AuthenticatedOperatorFlightData;
use App\Domain\FlightPlans\Support\FlightPlanDataNormalizer;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFlightPlanRequest;
use App\Models\Flight;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FlightPlanPreviewController extends Controller
{
    public function __construct(
        private FlightPlanQrRenderer $qrRenderer,
        private FlightPlanDataNormalizer $normalizer,
    ) {}

    public function store(StoreFlightPlanRequest $request): RedirectResponse
    {
        $validated = $this->normalizer->normalizePublicPreview($request->validated());
        $validated = AuthenticatedOperatorFlightData::apply($validated, Auth::user());

        $request->session()->put('flight_plan_preview', $validated);

        return redirect()->route('flightplan.preview');
    }

    public function previewFlightPlan(Request $request): View|RedirectResponse
    {
        $flightData = $request->session()->get('flight_plan_preview');

        if (! $flightData) {
            return redirect()->route('flightplan');
        }

        $flight = new Flight($flightData);
        $isPdfOnly = ! $this->normalizer->isRpusOperationalFlightData($flightData);

        return view('flightplan.pdf', [
            'flight' => $flight,
            'qrCodeBase64' => $isPdfOnly ? null : $this->qrRenderer->generateFlightPlanQrCodeBase64($flight),
            'isPreview' => true,
            'isPdfOnly' => $isPdfOnly,
            'previewActionLabel' => $isPdfOnly ? 'GENERATE PDF' : 'FILE FLIGHT PLAN',
            'previewActionHelp' => $isPdfOnly
                ? 'Creates a printable flight plan only. This will not be filed with RPUS and no QR code will be generated.'
                : 'Submit this flight plan to the RPUS Echo system for processing.',
            'previewActionUrl' => $isPdfOnly
                ? route('flightplan.pdf-only')
                : route('flightplan.approve'),
        ]);
    }

    public function editPreview(Request $request): RedirectResponse
    {
        $flightData = $request->session()->get('flight_plan_preview');

        if (! $flightData) {
            return redirect()->route('flightplan');
        }

        return redirect()
            ->route('flightplan')
            ->withInput($this->normalizer->prepareFlightPlanPreviewInput($flightData));
    }

    public function discardPreview(Request $request): RedirectResponse
    {
        $request->session()->forget('flight_plan_preview');

        return redirect()
            ->route('flightplan')
            ->with('discard_warning', 'Flight plan discarded.');
    }
}
