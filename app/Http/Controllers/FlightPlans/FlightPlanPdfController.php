<?php

namespace App\Http\Controllers\FlightPlans;

use App\Domain\FlightPlans\Services\FlightPlanPdfService;
use App\Domain\FlightPlans\Support\FlightPlanDataNormalizer;
use App\Http\Controllers\Controller;
use App\Http\Support\FlightPlanBrowserContext;
use App\Models\Flight;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FlightPlanPdfController extends Controller
{
    public function __construct(
        private FlightPlanPdfService $pdfs,
        private FlightPlanBrowserContext $browser,
        private FlightPlanDataNormalizer $normalizer,
    ) {}

    public function downloadPdf(Request $request, Flight $flight): StreamedResponse
    {
        $this->browser->ensureFlightAssetAccess($request, $flight);

        $storedPdfPath = $this->pdfs->resolveRequestedPath($request->query('file'))
            ?? $this->pdfs->storedPaths($flight)->first()
            ?? $this->pdfs->regenerate($flight);

        return Storage::disk('public')->download($storedPdfPath);
    }

    public function generatePdfOnly(Request $request): Response|RedirectResponse
    {
        $flightData = $request->session()->get('flight_plan_preview');

        if (! $flightData) {
            return redirect()->route('flightplan');
        }

        abort_if($this->normalizer->isRpusOperationalFlightData($flightData), 403);

        $flight = new Flight($flightData);

        return response($this->pdfs->renderPdfOnly($flight), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$this->pdfs->buildPdfOnlyFileName($flight).'"',
        ]);
    }
}
