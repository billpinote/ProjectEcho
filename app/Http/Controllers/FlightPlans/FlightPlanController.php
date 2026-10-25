<?php

namespace App\Http\Controllers\FlightPlans;

use App\Domain\FlightPlans\Enums\FlightPlanStatus;
use App\Domain\FlightPlans\Services\FlightPlanQrRenderer;
use App\Domain\FlightPlans\Services\FlightPlanSubmissionService;
use App\Domain\FlightPlans\Support\FlightPlanDataNormalizer;
use App\Filament\Panels\Pilot\Resources\Flights\FlightResource as PilotFlightResource;
use App\Filament\Panels\Pilot\Resources\MyArchivedFlights\MyArchivedFlightResource;
use App\Http\Controllers\Controller;
use App\Http\Support\FlightPlanBrowserContext;
use App\Models\Flight;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FlightPlanController extends Controller
{
    public function __construct(
        private FlightPlanQrRenderer $qrRenderer,
        private FlightPlanSubmissionService $submissions,
        private FlightPlanBrowserContext $browser,
        private FlightPlanDataNormalizer $normalizer,
    ) {}

    public function flightplan(): View
    {
        return view('flightplan.form', [
            'aircraftWtcMap' => $this->getAircraftWtcMap(),
            'prefilled' => [],
        ]);
    }

    public function approveFlightPlan(Request $request): RedirectResponse
    {
        $flightData = $request->session()->get('flight_plan_preview');

        if (! $flightData) {
            return redirect()->route('flightplan');
        }

        $user = Auth::user();

        abort_unless(
            $this->normalizer->isRpusOperationalFlightData($flightData)
            && $user !== null
            && $user->is_active
            && $user->canCreateFlightPlans(),
            403
        );

        ['flight' => $flight, 'pdf_path' => $storedPdfPath] = $this->submissions->submit($flightData, $user);

        $this->browser->grantSessionAccessToFlight($request, $flight);

        $request->session()->forget('flight_plan_preview');

        return redirect()
            ->route('flights.qr', [
                'flight' => $flight,
                'file' => basename($storedPdfPath),
            ]);
    }

    public function showFlightPlanView(Request $request, Flight $flight): View
    {
        $this->browser->ensureFlightUserAccess();
        abort_unless(Auth::user()?->can('view', $flight) ?? false, 403);

        if ($flight->requiresPicAuthorization() && ! $flight->isPicAuthorizationCurrent()) {
            return view('flightplan.pic-authorization-required', [
                'noticeHeading' => 'PIC Authorization Pending',
                'noticeMessage' => 'This flight plan is not yet available for operational review. PIC authorization must be completed first.',
            ]);
        }

        if (Auth::user()?->canReviewFlightPlans() && $flight->status === FlightPlanStatus::Pending && ! $flight->isPendingExpired()) {
            $flight->markAsReviewed();
        }

        $backActionUrl = $this->browser->roleAwarePanelUrl();
        $flight->loadMissing('manifest');

        return view('flightplan.pdf', [
            'flight' => $flight,
            'qrCodeBase64' => $this->qrRenderer->generateFlightPlanQrCodeBase64($flight),
            'isPreview' => true,
            'showPreviewActions' => false,
            'manifestReviewUrl' => $flight->manifest !== null && Auth::user()->can('view', $flight->manifest)
                ? route('flights.manifest.pdf', $flight)
                : null,
            'manifestReviewUnavailableReason' => $flight->manifest === null ? 'Manifest missing' : 'Manifest unavailable',
            'showReviewActions' => Auth::user()?->canReviewFlightPlans()
                && $flight->status === FlightPlanStatus::Pending
                && ! $flight->isPendingExpired(),
            'backActionUrl' => $backActionUrl,
            'backActionLabel' => 'Back to Dashboard',
            'backActionIsDashboard' => true,
            'acceptActionUrl' => route('flights.accept', $flight),
            'rejectActionUrl' => route('flights.reject', $flight),
            'acceptedByWiresign' => $this->browser->resolveAtcWiresign(),
            'picDeclineDetails' => $flight->pic_authorization_status === 'declined',
            'backActionLabel' => $flight->pic_authorization_status === 'declined' ? 'Back to Archive' : 'Back to Dashboard',
            'backActionUrl' => $flight->pic_authorization_status === 'declined'
                ? MyArchivedFlightResource::getUrl('index', panel: 'pilot')
                : $backActionUrl,
            'correctResubmitUrl' => $flight->pic_authorization_status === 'declined'
                && (int) $flight->prepared_by_user_id === (int) Auth::id()
                ? PilotFlightResource::getUrl('create', ['correct_from' => $flight->getKey()], panel: 'pilot')
                : null,
        ]);
    }

    private function getAircraftWtcMap(): array
    {
        if (! Schema::hasTable('aircraft_types_wtc')) {
            return [];
        }

        return DB::table('aircraft_types_wtc')
            ->whereNotNull('icao_legacy_wtc')
            ->whereNotNull('icao_type_designator')
            ->pluck('icao_legacy_wtc', 'icao_type_designator')
            ->mapWithKeys(fn (mixed $wtc, mixed $designator): array => [
                strtoupper(trim((string) $designator)) => strtoupper(trim((string) $wtc)),
            ])
            ->all();
    }
}
