<?php

namespace App\Http\Controllers\FlightPlans;

use App\Domain\FlightPlans\Services\FilingAuthorizationService;
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
        $selfPrepared = (int) $flight->prepared_by_user_id === (int) Auth::id()
            && (int) $flight->pilot_in_command_user_id === (int) Auth::id()
            && Auth::user()->isPilot() && FlightAccess::canView(Auth::user(), $flight);
        abort_unless($selfPrepared || FlightAccess::canAccessPicAuthorization(Auth::user(), $flight), 403);

        try {
            $this->authorizations->guardEligiblePic(Auth::user());
        } catch (ValidationException) {
            return view('flightplan.pic-authorization-required', [
                'backActionUrl' => $this->browser->picAuthorizationScannerUrl(),
                'backActionLabel' => 'BACK TO PIC AUTHORIZATION SCANNER',
            ]);
        }

        $payload = $this->payloads->buildPayload($flight);
        $package = app(FilingAuthorizationService::class)->assertReviewedPackageCurrent($flight);

        return view('flightplan.pdf', [
            'flight' => $flight,
            'filingPackage' => $package,
            'filingToken' => $token,
            'canAuthorizeFiling' => $this->authorizations->canReviewFiling(Auth::user(), $flight),
            'authorizationUnavailableReason' => $this->authorizations->authorizationUnavailableReason($flight, Auth::user()),
            'hasApprovedPilotSignature' => Auth::user()->approvedSignature()->where('status', 'approved')->exists(),
            'selfFilingAuthorization' => (int) $flight->prepared_by_user_id === (int) Auth::id()
                && (int) $flight->pilot_in_command_user_id === (int) Auth::id(),
            'qrCodeBase64' => $payload !== null ? $this->qrRenderer->generateQrCodeBase64FromPayload($payload) : null,
            'isPreview' => true,
            'showPreviewActions' => false,
            'showReviewActions' => false,
            'backActionUrl' => $this->browser->picAuthorizationScannerUrl(),
            'backActionLabel' => 'BACK TO PIC AUTHORIZATION SCANNER',
        ]);
    }
}
