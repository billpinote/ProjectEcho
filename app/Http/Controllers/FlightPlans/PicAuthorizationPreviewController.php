<?php

namespace App\Http\Controllers\FlightPlans;

use App\Domain\FlightPlans\Services\FlightPlanQrPayloadService;
use App\Domain\FlightPlans\Services\FlightPlanQrRenderer;
use App\Domain\FlightPlans\Services\PicAuthorizationService;
use App\Domain\FlightPlans\Support\FlightAccess;
use App\Http\Controllers\Controller;
use App\Http\Support\FlightPlanBrowserContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class PicAuthorizationPreviewController extends Controller
{
    public function __construct(
        private PicAuthorizationService $authorizations,
        private FlightPlanQrPayloadService $payloads,
        private FlightPlanQrRenderer $qrRenderer,
        private FlightPlanBrowserContext $browser,
    ) {}

    public function previewPicAuthorizationFlightPlan(Request $request, string $token): View
    {
        $this->browser->ensureFlightUserAccess();

        $flight = $this->authorizations->resolveAuthorizationHandoff($token);
        abort_unless($flight !== null, 403);
        abort_unless(FlightAccess::canAccessPicAuthorization(Auth::user(), $flight), 403);

        try {
            $this->authorizations->guardEligiblePic(Auth::user());
        } catch (ValidationException) {
            return view('flightplan.pic-authorization-required', [
                'backActionUrl' => $this->browser->picAuthorizationScannerUrl(),
                'backActionLabel' => 'BACK TO PIC AUTHORIZATION SCANNER',
            ]);
        }

        $payload = $this->payloads->buildPayload($flight);

        return view('flightplan.pdf', [
            'flight' => $flight,
            'qrCodeBase64' => $payload !== null ? $this->qrRenderer->generateQrCodeBase64FromPayload($payload) : null,
            'isPreview' => true,
            'showPreviewActions' => false,
            'showReviewActions' => false,
            'backActionUrl' => $this->browser->picAuthorizationScannerUrl(),
            'backActionLabel' => 'BACK TO PIC AUTHORIZATION SCANNER',
        ]);
    }
}
