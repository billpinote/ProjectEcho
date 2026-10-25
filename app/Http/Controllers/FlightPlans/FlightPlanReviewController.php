<?php

namespace App\Http\Controllers\FlightPlans;

use App\Domain\FlightPlans\Services\FlightPlanMutationService;
use App\Domain\FlightPlans\Services\FlightPlanPdfService;
use App\Http\Controllers\Controller;
use App\Http\Requests\RejectFlightPlanRequest;
use App\Http\Support\FlightPlanBrowserContext;
use App\Models\Flight;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class FlightPlanReviewController extends Controller
{
    public function __construct(
        private FlightPlanMutationService $mutations,
        private FlightPlanPdfService $pdfs,
        private FlightPlanBrowserContext $browser,
    ) {}

    public function acceptFlightPlan(Flight $flight): RedirectResponse
    {
        $this->browser->ensureReviewerAccess();
        abort_unless(Auth::user()?->can('accept', $flight) ?? false, 403);

        if ($flight->isPendingExpired()) {
            return redirect()
                ->route('flights.view', $flight)
                ->with('review_status', $flight->expiration_reason ?? 'Flight plan expired because the date of flight has passed.');
        }

        $flight = $this->mutations->accept($flight, Auth::user(), $this->browser->resolveAtcWiresign());

        $this->pdfs->regenerate($flight);

        return redirect()
            ->route('flights.view', $flight)
            ->with('review_status', sprintf(
                'Flight plan accepted by %s. You may CLOSE this page now.',
                $this->browser->resolveAtcWiresign() !== '' ? $this->browser->resolveAtcWiresign() : 'ATC'
            ));
    }

    public function rejectFlightPlan(RejectFlightPlanRequest $request, Flight $flight): RedirectResponse
    {
        $this->browser->ensureReviewerAccess();
        abort_unless(Auth::user()?->can('reject', $flight) ?? false, 403);

        $validated = $request->validated();

        $this->mutations->reject($flight, Auth::user(), $this->browser->resolveAtcWiresign(), (string) $validated['rejection_reason']);

        return redirect()
            ->route('flights.view', $flight)
            ->with('review_status', sprintf(
                'Flight plan rejected by %s.',
                $this->browser->resolveAtcWiresign() !== '' ? $this->browser->resolveAtcWiresign() : 'ATC'
            ));
    }
}
