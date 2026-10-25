<?php

namespace App\Http\Controllers\FlightPlans;

use App\Domain\FlightPlans\Services\FlightPlanQrLookupService;
use App\Domain\FlightPlans\Services\FlightPlanQrPayloadService;
use App\Domain\FlightPlans\Services\FlightPlanQrRenderer;
use App\Domain\FlightPlans\Support\FlightAccess;
use App\Domain\FlightPlans\Support\FlightPlanDataNormalizer;
use App\Http\Controllers\Controller;
use App\Http\Requests\LookupFlightPlanQrRequest;
use App\Http\Support\FlightPlanBrowserContext;
use App\Models\Flight;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FlightPlanScanController extends Controller
{
    public function __construct(
        private FlightPlanQrRenderer $qrRenderer,
        private FlightPlanBrowserContext $browser,
        private FlightPlanDataNormalizer $normalizer,
        private FlightPlanQrLookupService $lookup,
        private FlightPlanQrPayloadService $payloads,
    ) {}

    public function scanQr(): View
    {
        $this->browser->ensurePilotCannotScanQr();

        return view('flightplan.scan-qr', [
            'payload' => old('payload', ''),
            'matchedFlight' => null,
        ]);
    }

    public function lookupScanQr(LookupFlightPlanQrRequest $request): View|RedirectResponse
    {
        $this->browser->ensurePilotCannotScanQr();

        $validated = $request->validated();

        $matchedFlight = $this->lookup->lookup((string) $validated['payload']);

        if ($matchedFlight === null) {
            return back()
                ->withErrors([
                    'payload' => $this->payloads->invalidPayloadMessage((string) $validated['payload']),
                ])
                ->withInput();
        }

        $flight = $matchedFlight['flight'];
        $preview = $matchedFlight['preview'];
        $matchedFlight['can_open'] = $flight === null
            ? ! Auth::check()
            : (Auth::check()
                ? (Auth::user()?->can('view', $flight) ?? false)
                : $this->browser->sessionCanAccessFlight($request, $flight));
        $matchedFlight['view_url'] = $preview !== null
            ? route('flightplan.scan-qr.preview', ['token' => $this->browser->storeScannedFlightPlanPreview($request, $preview)])
            : route('flights.view', $flight);
        unset($matchedFlight['flight'], $matchedFlight['preview']);

        return view('flightplan.scan-qr', [
            'payload' => trim((string) $validated['payload']),
            'matchedFlight' => $matchedFlight,
        ]);
    }

    public function editFromQr(LookupFlightPlanQrRequest $request): RedirectResponse
    {
        $this->browser->ensurePilotCannotScanQr();

        $validated = $request->validated();

        $parsedPayload = $this->payloads->parsePayload((string) $validated['payload']);

        if ($parsedPayload === null || ($parsedPayload['format'] ?? null) !== 'v2-offline') {
            return back()->withErrors([
                'payload' => 'Invalid or unsupported QR payload format. Only signed offline (V2) payloads can be edited.',
            ]);
        }

        $snapshot = $parsedPayload['snapshot'] ?? null;

        if (! is_array($snapshot)) {
            return back()->withErrors([
                'payload' => 'Unable to decode the QR payload.',
            ]);
        }

        return redirect()
            ->route('flightplan')
            ->withInput($this->normalizer->prepareFlightPlanPreviewInput($snapshot));
    }

    public function previewScannedFlightPlan(Request $request, string $token): View|RedirectResponse
    {
        $this->browser->ensurePilotCannotScanQr();

        $preview = $request->session()->get('scanned_flight_plan_previews.'.$token);

        if (! is_array($preview) || ! isset($preview['snapshot']) || ! is_array($preview['snapshot'])) {
            return redirect()
                ->route('flightplan.scan-qr')
                ->withErrors(['payload' => 'That scanned flight-plan preview is no longer available in this browser session.']);
        }

        $flight = null;
        if (isset($preview['flight_id']) && is_numeric($preview['flight_id'])) {
            $flight = Flight::find((int) $preview['flight_id']);

            if ($flight !== null && Auth::check()) {
                $user = Auth::user();
                $canView = $user?->can('view', $flight) ?? false;
                $canReadDeclinedPicScan = $flight->isPicAuthorizationDeclined()
                    && FlightAccess::canAccessPicAuthorization($user, $flight);

                abort_unless($canView || $canReadDeclinedPicScan, 403);
            }
        }

        if (! $flight) {
            $flight = new Flight($this->normalizer->preparePreviewFlightAttributes($preview['snapshot']));
        }

        return view('flightplan.pdf', [
            'flight' => $flight,
            'qrCodeBase64' => isset($preview['payload']) && is_string($preview['payload'])
                ? $this->qrRenderer->generateQrCodeBase64FromPayload($preview['payload'])
                : null,
            'isPreview' => true,
            'showPreviewActions' => false,
            'showReviewActions' => false,
        ]);
    }
}
