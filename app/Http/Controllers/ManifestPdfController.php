<?php

namespace App\Http\Controllers;

use App\Domain\FlightPlans\Services\FlightManifestService;
use App\Domain\FlightPlans\Services\ManifestPdfService;
use App\Http\Requests\GuestManifestPdfRequest;
use App\Models\Flight;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ManifestPdfController extends Controller
{
    public function __construct(private ManifestPdfService $pdf, private FlightManifestService $manifests) {}

    public function show(Request $request, Flight $flight): Response
    {
        $manifest = $this->manifests->view($flight, $request->user());

        return $this->response($this->pdf->forFlight($flight, $manifest), 'manifest-flight-'.$flight->id.'.pdf', $request->boolean('download'));
    }

    public function blank(Request $request): Response
    {
        abort_unless($request->user()?->is_active && $request->user()->canViewFlightPlans(), 403);

        return $this->response(file_get_contents($this->pdf->templatePath()), 'caap-dom-flight-manifest-blank.pdf', true);
    }

    public function guestForm(): View
    {
        return view('flightplan.guest-manifest');
    }

    public function guest(GuestManifestPdfRequest $request): Response
    {
        return $this->response($this->pdf->render($request->validated()), 'guest-flight-manifest.pdf', $request->boolean('download'));
    }

    private function response(string $bytes, string $filename, bool $download): Response
    {
        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($download ? 'attachment' : 'inline').'; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
