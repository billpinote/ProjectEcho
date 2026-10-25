<?php

namespace App\Http\Controllers\FlightPlans;

use App\Domain\FlightPlans\Services\FlightPlanPdfService;
use App\Domain\FlightPlans\Services\FlightPlanQrRenderer;
use App\Http\Controllers\Controller;
use App\Http\Support\FlightPlanBrowserContext;
use App\Models\Flight;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FlightPlanQrController extends Controller
{
    public function __construct(
        private FlightPlanQrRenderer $qrRenderer,
        private FlightPlanPdfService $pdfs,
        private FlightPlanBrowserContext $browser,
    ) {}

    public function showQr(Request $request, Flight $flight): View
    {
        $this->browser->ensureFlightAssetAccess($request, $flight);

        $storedPdfPath = $this->pdfs->resolveRequestedPath($request->query('file'))
            ?? $this->pdfs->storedPaths($flight)->first()
            ?? $this->pdfs->regenerate($flight);

        return view('flightplan.qr', [
            'flight' => $flight,
            'qrCodeBase64' => $this->qrRenderer->generateFlightPlanQrCodeBase64($flight, 720, 4),
            'pdfDownloadUrl' => route('flights.pdf.download', [
                'flight' => $flight,
                'file' => basename($storedPdfPath),
            ]),
            'qrImageDownloadUrl' => route('flights.qr.download', [
                'flight' => $flight,
            ]),
            'qrImageFileName' => $this->qrRenderer->buildQrImageFileName($flight),
            'backActionUrl' => $this->browser->roleAwarePanelUrl(),
        ]);
    }

    public function downloadQrImage(Request $request, Flight $flight): Response
    {
        $this->browser->ensureFlightAssetAccess($request, $flight);
        $this->qrRenderer->ensureGdExtensionIsLoaded();

        $fileName = $this->qrRenderer->buildQrImageFileName($flight);
        $png = $this->qrRenderer->generateFlightPlanQrCardPng($flight);

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }
}
