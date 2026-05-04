<?php

namespace App\Http\Controllers;

use App\Enums\FlightPlanStatus;
use App\Filament\Resources\Flights\Schemas\FlightForm;
use App\Filament\Resources\Reports\AbbreviatedFlightReportResource;
use App\Http\Requests\StoreFlightPlanRequest;
use App\Models\Flight;
use App\Rules\UtcFourDigitTime;
use App\Services\FlightPlanQrPayloadService;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class FlightController extends Controller
{
    /**
     * Store form data in session and show preview.
     */
    public function store(StoreFlightPlanRequest $request)
    {
        $validated = $request->validated();
        foreach (['proposed_time', 'total_eet', 'endurance'] as $field) {
            if (array_key_exists($field, $validated)) {
                $validated[$field] = UtcFourDigitTime::normalizeForStorage($validated[$field]);
            }
        }
        $validated['date_of_filing'] = $validated['date_of_filing'] ?? now('UTC')->toDateString();
        $validated = $this->uppercaseStringFlightFields($validated);
        $validated = $this->normalizeNumericFlightFields($validated);

        // Store validated data in session instead of creating DB record
        $request->session()->put('flight_plan_preview', $validated);

        return redirect()->route('flightplan.preview');
    }

    /**
     * Show the blank flight plan form.
     */
    public function flightplan()
    {
        $aircraftWtcMap = DB::table('aircraft_types_wtc')
            ->whereNotNull('icao_legacy_wtc')
            ->whereNotNull('icao_type_designator')
            ->pluck('icao_legacy_wtc', 'icao_type_designator')
            ->mapWithKeys(fn (mixed $wtc, mixed $designator): array => [
                strtoupper(trim((string) $designator)) => strtoupper(trim((string) $wtc)),
            ])
            ->all();

        return view('flightplan.form', [
            'aircraftWtcMap' => $aircraftWtcMap,
            'prefilled' => [],
        ]);
    }

    /**
     * Show the public QR scan/upload page.
     */
    public function scanQr()
    {
        return view('flightplan.scan-qr', [
            'payload' => old('payload', ''),
            'matchedFlight' => null,
        ]);
    }

    /**
     * Resolve a QR payload from the public scan/upload page.
     */
    public function lookupScanQr(Request $request)
    {
        $validated = $request->validate([
            'payload' => ['required', 'string', 'max:20000'],
        ], [
            'payload.required' => 'Paste or scan a QR payload first.',
        ]);

        $matchedFlight = $this->buildMatchedFlightFromPayload(
            $request,
            (string) $validated['payload'],
        );

        if ($matchedFlight === null) {
            return back()
                ->withErrors([
                    'payload' => 'Expected a valid Echo QR payload. V2 signed offline payloads and legacy V1 database payloads are supported.',
                ])
                ->withInput();
        }

        return view('flightplan.scan-qr', [
            'payload' => trim((string) $validated['payload']),
            'matchedFlight' => $matchedFlight,
        ]);
    }

    /**
     * Show the flight plan form prefilled with QR payload data for editing.
     */
    public function editFromQr(Request $request)
    {
        $validated = $request->validate([
            'payload' => ['required', 'string', 'max:20000'],
        ], [
            'payload.required' => 'A QR payload is required.',
        ]);

        $parsedPayload = $this->qrPayloads()->parsePayload((string) $validated['payload']);

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
            ->withInput($this->prepareFlightPlanPreviewInput($snapshot));
    }

    /**
     * Generate and download the saved flight plan PDF.
     */
    public function downloadPdf(Request $request, Flight $flight)
    {
        $this->ensureFlightAssetAccess($request, $flight);

        $storedPdfPath = $this->resolveRequestedPdfPath($request)
            ?? $this->findExistingFlightPlanPdfPath($flight)
            ?? $this->storeFlightPlanPdf($flight);

        return Storage::disk('public')->download($storedPdfPath);
    }

    /**
     * Show the approved flight plan QR code for ATC processing.
     */
    public function showQr(Request $request, Flight $flight)
    {
        $this->ensureFlightAssetAccess($request, $flight);

        $storedPdfPath = $this->resolveRequestedPdfPath($request)
            ?? $this->findExistingFlightPlanPdfPath($flight)
            ?? $this->storeFlightPlanPdf($flight);

        return view('flightplan.qr', [
            'flight' => $flight,
            'qrCodeBase64' => $this->generateFlightPlanQrCodeBase64($flight, 720, 4),
            'pdfDownloadUrl' => route('flights.pdf.download', [
                'flight' => $flight,
                'file' => basename($storedPdfPath),
            ]),
            'qrImageDownloadUrl' => route('flights.qr.download', [
                'flight' => $flight,
            ]),
        ]);
    }

    /**
     * Show a saved flight plan using the same preview layout as the PDF template.
     */
    public function showFlightPlanView(Request $request, Flight $flight)
    {
        $this->ensureFlightUserAccess();

        if (Auth::user()?->canReviewFlightPlans() && $flight->status === FlightPlanStatus::Pending && ! $flight->isPendingExpired()) {
            $flight->markAsReviewed();
        }

        $backActionUrl = $this->resolveReviewBackUrl($request, $flight);

        return view('flightplan.pdf', [
            'flight' => $flight,
            'qrCodeBase64' => $this->generateFlightPlanQrCodeBase64($flight),
            'isPreview' => true,
            'showPreviewActions' => false,
            'showReviewActions' => Auth::user()?->canReviewFlightPlans()
                && $flight->status === FlightPlanStatus::Pending
                && ! $flight->isPendingExpired(),
            'backActionUrl' => $backActionUrl,
            'acceptActionUrl' => route('flights.accept', $flight),
            'rejectActionUrl' => route('flights.reject', $flight),
            'acceptedByWiresign' => $this->resolveAtcWiresign(),
        ]);
    }

    /**
     * Stream the abbreviated RPUS report as an inline A4 landscape PDF.
     */
    public function downloadAbbreviatedReportPdf(Request $request)
    {
        $this->ensureReviewerAccess();

        $generatedAt = now('UTC');
        $selectedDate = (string) ($request->query('date') ?: now('UTC')->toDateString());
        $flights = AbbreviatedFlightReportResource::getEloquentQuery()
            ->whereDate('date_of_flight', $selectedDate)
            ->orderByRaw('case when date_of_flight is null then 1 else 0 end')
            ->orderBy('date_of_flight')
            ->orderByRaw('case when proposed_time is null then 1 else 0 end')
            ->orderBy('proposed_time')
            ->orderBy('id')
            ->get();

        $pdf = Pdf::loadView('reports.abbreviated-flight-report-pdf', [
            'flights' => $flights,
            'generatedAt' => $generatedAt,
            'selectedDate' => $selectedDate,
            'generatedBy' => $this->resolveAtcWiresign(),
            'formatTime' => static fn (?string $time): ?string => FlightForm::formatTimeForForm($time),
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('abbreviated-flight-report-'.$generatedAt->format('Y-m-d-His').'.pdf');
    }

    /**
     * Accept a pending flight plan and stamp the ATC acceptance details.
     */
    public function acceptFlightPlan(Flight $flight)
    {
        $this->ensureReviewerAccess();

        if ($flight->isPendingExpired()) {
            return redirect()
                ->route('flights.view', $flight)
                ->with('review_status', $flight->expiration_reason ?? 'Flight plan expired because the date of flight has passed.');
        }

        $nowUtc = now('UTC');

        $flight->forceFill([
            'status' => FlightPlanStatus::Accepted,
            'accepted_by_user_id' => Auth::id(),
            'accepted_by_wiresign' => $this->resolveAtcWiresign(),
            'rejected_by_wiresign' => null,
            'rejection_reason' => null,
            'received_by' => $this->resolveAtcWiresign(),
            'received_date' => $nowUtc->toDateString(),
            'received_time' => $nowUtc->format('H:i'),
            'received_facility' => (string) (Auth::user()?->station ?? ''),
        ])->save();

        $this->deleteStoredFlightPlanPdfs($flight);
        $this->storeFlightPlanPdf($flight);

        return redirect()
            ->route('flights.view', $flight)
            ->with('review_status', sprintf(
                'Flight plan accepted by %s. You may CLOSE this page now.',
                $this->resolveAtcWiresign() !== '' ? $this->resolveAtcWiresign() : 'ATC'
            ));
    }

    /**
     * Reject a pending flight plan.
     */
    public function rejectFlightPlan(Request $request, Flight $flight)
    {
        $this->ensureReviewerAccess();

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:255'],
        ]);

        $flight->forceFill([
            'status' => FlightPlanStatus::Rejected,
            'accepted_by_user_id' => null,
            'accepted_by_wiresign' => null,
            'rejected_by_wiresign' => $this->resolveAtcWiresign(),
            'rejection_reason' => trim((string) $validated['rejection_reason']),
            'received_by' => null,
            'received_date' => null,
            'received_time' => null,
            'received_facility' => null,
        ])->save();

        return redirect()
            ->route('flights.view', $flight)
            ->with('review_status', sprintf(
                'Flight plan rejected by %s.',
                $this->resolveAtcWiresign() !== '' ? $this->resolveAtcWiresign() : 'ATC'
            ));
    }

    /**
     * Download a server-rendered PNG card containing the approved QR code.
     */
    public function downloadQrImage(Request $request, Flight $flight)
    {
        $this->ensureFlightAssetAccess($request, $flight);
        $this->ensureGdExtensionIsLoaded();

        $fileName = $this->buildQrImageFileName($flight);
        $png = $this->generateFlightPlanQrCardPng($flight);

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    /**
     * Show the flight plan preview before PDF generation.
     */
    public function previewFlightPlan(Request $request)
    {
        $flightData = $request->session()->get('flight_plan_preview');

        if (! $flightData) {
            return redirect()->route('flightplan');
        }

        // Convert array → model (important!)
        $flight = new Flight($flightData);

        return view('flightplan.pdf', [
            'flight' => $flight,
            'qrCodeBase64' => $this->generateFlightPlanQrCodeBase64($flight),
            'isPreview' => true,
        ]);
    }

    /**
     * Show a read-only preview reconstructed from a scanned signed QR payload.
     */
    public function previewScannedFlightPlan(Request $request, string $token)
    {
        $preview = $request->session()->get('scanned_flight_plan_previews.'.$token);

        if (! is_array($preview) || ! isset($preview['snapshot']) || ! is_array($preview['snapshot'])) {
            return redirect()
                ->route('flightplan.scan-qr')
                ->withErrors(['payload' => 'That scanned flight-plan preview is no longer available in this browser session.']);
        }

        // Try to load the actual flight record if it exists in the database
        // This ensures CAAP acceptance details are shown for accepted flights
        $flight = null;
        if (isset($preview['flight_id']) && is_numeric($preview['flight_id'])) {
            $flight = Flight::find((int) $preview['flight_id']);
        }

        // Fall back to creating a flight from the snapshot data if no database record exists
        if (! $flight) {
            $flight = new Flight($this->preparePreviewFlightAttributes($preview['snapshot']));
        }

        return view('flightplan.pdf', [
            'flight' => $flight,
            'qrCodeBase64' => isset($preview['payload']) && is_string($preview['payload'])
                ? $this->generateQrCodeBase64FromPayload($preview['payload'])
                : null,
            'isPreview' => true,
            'showPreviewActions' => false,
            'showReviewActions' => false,
        ]);
    }

    /**
     * Approve the flight plan and generate PDF with QR code.
     */
    public function approveFlightPlan(Request $request)
    {
        $flightData = $request->session()->get('flight_plan_preview');

        if (! $flightData) {
            return redirect()->route('flightplan');
        }

        // Create the Flight record
        $flight = Flight::create($flightData);

        // Generate PDF and QR code
        $storedPdfPath = $this->storeFlightPlanPdf($flight);

        $this->grantSessionAccessToFlight($request, $flight);

        // Clear session data
        $request->session()->forget('flight_plan_preview');

        return redirect()
            ->route('flights.qr', [
                'flight' => $flight,
                'file' => basename($storedPdfPath),
            ]);
    }

    /**
     * Return to the form with preview data available for editing.
     */
    public function editPreview(Request $request)
    {
        $flightData = $request->session()->get('flight_plan_preview');

        if (! $flightData) {
            return redirect()->route('flightplan');
        }

        return redirect()
            ->route('flightplan')
            ->withInput($this->prepareFlightPlanPreviewInput($flightData));
    }

    /**
     * Discard the flight plan preview.
     */
    public function discardPreview(Request $request)
    {
        $request->session()->forget('flight_plan_preview');

        return redirect()
            ->route('flightplan')
            ->with('discard_warning', 'Flight plan discarded.');
    }

    /**
     * Convert session-preview values into the field shape expected by the form.
     *
     * @param  array<string, mixed>  $flightData
     * @return array<string, mixed>
     */
    private function prepareFlightPlanPreviewInput(array $flightData): array
    {
        foreach (['proposed_time', 'total_eet', 'endurance'] as $field) {
            if (isset($flightData[$field]) && is_string($flightData[$field])) {
                $flightData[$field] = str_replace(':', '', $flightData[$field]);
            }
        }

        return $flightData;
    }

    /**
     * Prepare preview-only attributes that are normally derived during form submission.
     *
     * @param  array<string, mixed>  $flightData
     * @return array<string, mixed>
     */
    private function preparePreviewFlightAttributes(array $flightData): array
    {
        $otherInformation = (string) ($flightData['other_information'] ?? '');

        $tagMap = [
            'other_info_dof' => 'DOF',
            'other_info_rmk' => 'RMK',
            'other_info_typ' => 'TYP',
            'other_info_dep' => 'DEP',
            'other_info_route' => 'RTE',
            'other_info_dest' => 'DEST',
            'other_info_altn_1' => 'ALTN',
            'other_info_altn_2' => 'ALTN2',
            'other_info_pbn' => 'PBN',
            'other_info_reg' => 'REG',
            'other_info_opr' => 'OPR',
        ];

        foreach ($tagMap as $field => $tag) {
            if (! array_key_exists($field, $flightData) || blank($flightData[$field])) {
                $flightData[$field] = $this->extractOtherInfoTagValue($tag, $otherInformation);
            }
        }

        return $flightData;
    }

    private function extractOtherInfoTagValue(string $tag, string $text): ?string
    {
        if ($text === '') {
            return null;
        }

        $tagWithSlash = $tag.'/';
        $tagPos = stripos($text, $tagWithSlash);

        if ($tagPos === false) {
            return null;
        }

        $startPos = $tagPos + strlen($tagWithSlash);
        $remainingText = substr($text, $startPos);

        if (preg_match('/\s+[A-Z0-9]{2,5}\//i', $remainingText, $matches, PREG_OFFSET_CAPTURE)) {
            $value = substr($remainingText, 0, $matches[0][1]);
        } else {
            $value = $remainingText;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * Normalize numeric string fields before saving to integer columns.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizeNumericFlightFields(array $validated): array
    {
        foreach (['persons_on_board', 'dinghies_number', 'dinghies_capacity'] as $field) {
            if (array_key_exists($field, $validated) && $validated[$field] !== null && $validated[$field] !== '') {
                $validated[$field] = (int) $validated[$field];
            }
        }

        return $validated;
    }

    /**
     * Uppercase string values before saving to the database.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function uppercaseStringFlightFields(array $validated): array
    {
        foreach ($validated as $field => $value) {
            if (is_string($value)) {
                $validated[$field] = strtoupper(trim($value));
            }
        }

        return $validated;
    }

    /**
     * Render and store the flight plan PDF on the public disk.
     */
    private function storeFlightPlanPdf(Flight $flight): string
    {
        $folderName = now('UTC')->format('Ymd');
        $fileName = $this->resolveFlightPlanPdfFileName($flight, $folderName);
        $storagePath = 'flight-plans/'.$folderName.'/'.$fileName;

        $pdf = Pdf::loadView('flightplan.pdf', [
            'flight' => $flight,
            'qrCodeBase64' => $this->generateFlightPlanQrCodeBase64($flight),
        ])->setPaper('a4', 'portrait');

        Storage::disk('public')->put($storagePath, $pdf->output());

        return $storagePath;
    }

    /**
     * Generate the QR code payload used in preview and PDF output.
     */
    private function generateFlightPlanQrCodeBase64(Flight $flight, int $size = 250, int $margin = 2): ?string
    {
        $payload = $this->qrPayloads()->buildPayload($flight);

        return $payload !== null
            ? $this->generateQrCodeBase64FromPayload($payload, $size, $margin)
            : null;
    }

    private function generateQrCodeBase64FromPayload(string $payload, int $size = 250, int $margin = 2): string
    {
        $qrCodeSvg = QrCode::size($size)->margin($margin)->format('svg')->generate($payload);

        return 'data:image/svg+xml;base64,'.base64_encode($qrCodeSvg);
    }

    /**
     * Generate a mobile-friendly PNG card without relying on browser screenshots.
     */
    private function generateFlightPlanQrCardPng(Flight $flight): string
    {
        $payload = $this->qrPayloads()->buildPayload($flight);

        if ($payload === null) {
            abort(404, 'QR payload is not available for this flight plan.');
        }

        $width = 1080;
        $height = 1680;
        $image = imagecreatetruecolor($width, $height);

        $background = imagecolorallocate($image, 244, 246, 238);
        $card = imagecolorallocate($image, 255, 253, 247);
        $ink = imagecolorallocate($image, 22, 32, 24);
        $muted = imagecolorallocate($image, 104, 114, 107);
        $accent = imagecolorallocate($image, 15, 95, 74);
        $soft = imagecolorallocate($image, 233, 238, 231);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);

        imagefill($image, 0, 0, $background);
        $this->drawRoundedRectangle($image, 36, 36, $width - 36, $height - 36, 48, $card);

        $regularFont = $this->resolveQrFontPath(false);
        $boldFont = $this->resolveQrFontPath(true);

        $this->drawCenteredText($image, 'FLIGHT PLAN READY', 22, 190, $accent, $boldFont, 2);
        $this->drawCenteredText($image, 'Show This QR To ATC', 52, 280, $ink, $regularFont);
        $this->drawCenteredText($image, 'Keep this image on your phone or tablet', 24, 345, $muted, $regularFont);
        $this->drawCenteredText($image, 'and show it to ATC for processing.', 24, 385, $muted, $regularFont);

        $qrOuterX = 100;
        $qrOuterY = 450;
        $qrOuterSize = 880;
        $this->drawRoundedRectangle($image, $qrOuterX, $qrOuterY, $qrOuterX + $qrOuterSize, $qrOuterY + $qrOuterSize, 34, $soft);
        imagefilledrectangle($image, $qrOuterX + 42, $qrOuterY + 42, $qrOuterX + $qrOuterSize - 42, $qrOuterY + $qrOuterSize - 42, $white);
        $this->drawQrCode($image, $payload, $qrOuterX + 78, $qrOuterY + 78, $qrOuterSize - 156, 4, $black, $white);

        $metaTop = 1380;
        $boxWidth = 410;
        $boxHeight = 110;
        $leftX = 100;
        $rightX = $width - 100 - $boxWidth;

        $this->drawMetaBox($image, $leftX, $metaTop, $boxWidth, $boxHeight, 'CALL SIGN', (string) ($flight->aircraft_identification ?? 'N/A'), $soft, $muted, $ink, $regularFont, $boldFont);
        $this->drawMetaBox($image, $rightX, $metaTop, $boxWidth, $boxHeight, 'DOF', $this->formatQrDate($flight), $soft, $muted, $ink, $regularFont, $boldFont);
        $this->drawMetaBox($image, $leftX, $metaTop + 140, $boxWidth, $boxHeight, 'DEPARTURE', (string) ($flight->departure_aerodrome ?? 'N/A'), $soft, $muted, $ink, $regularFont, $boldFont);
        $this->drawMetaBox($image, $rightX, $metaTop + 140, $boxWidth, $boxHeight, 'PTD', $this->formatQrTime($flight), $soft, $muted, $ink, $regularFont, $boldFont);

        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return $png;
    }

    private function ensureGdExtensionIsLoaded(): void
    {
        if (extension_loaded('gd') && function_exists('imagecreatetruecolor')) {
            return;
        }

        abort(500, 'QR image downloads require the PHP GD extension. Enable extension=gd in php.ini and restart the web server.');
    }

    private function drawQrCode($image, string $payload, int $x, int $y, int $targetSize, int $quietZone, int $black, int $white): void
    {
        $qrCode = Encoder::encode($payload, ErrorCorrectionLevel::M());
        $matrix = $qrCode->getMatrix();
        $matrixSize = $matrix->getWidth();
        $moduleSize = max(1, (int) floor($targetSize / ($matrixSize + ($quietZone * 2))));
        $qrSize = ($matrixSize + ($quietZone * 2)) * $moduleSize;
        $offsetX = $x + (int) floor(($targetSize - $qrSize) / 2);
        $offsetY = $y + (int) floor(($targetSize - $qrSize) / 2);

        imagefilledrectangle($image, $x, $y, $x + $targetSize, $y + $targetSize, $white);

        for ($row = 0; $row < $matrixSize; $row++) {
            for ($col = 0; $col < $matrixSize; $col++) {
                if ($matrix->get($col, $row) !== 1) {
                    continue;
                }

                $left = $offsetX + (($col + $quietZone) * $moduleSize);
                $top = $offsetY + (($row + $quietZone) * $moduleSize);

                imagefilledrectangle(
                    $image,
                    $left,
                    $top,
                    $left + $moduleSize - 1,
                    $top + $moduleSize - 1,
                    $black
                );
            }
        }
    }

    private function drawMetaBox($image, int $x, int $y, int $width, int $height, string $label, string $value, int $background, int $labelColor, int $valueColor, ?string $regularFont, ?string $boldFont): void
    {
        $this->drawRoundedRectangle($image, $x, $y, $x + $width, $y + $height, 24, $background);
        $this->drawText($image, $label, 16, $x + 28, $y + 38, $labelColor, $boldFont, 2);
        $this->drawTextCenteredInBox($image, strtoupper($value), 24, $x, $y + 84, $width, $valueColor, $boldFont);
    }

    private function drawCenteredText($image, string $text, int $size, int $baselineY, int $color, ?string $fontPath, int $letterSpacing = 0): void
    {
        $text = $letterSpacing > 0 ? implode(str_repeat(' ', $letterSpacing), str_split($text)) : $text;
        $textWidth = $this->measureTextWidth($text, $size, $fontPath);
        $x = (int) ((imagesx($image) - $textWidth) / 2);
        $this->drawText($image, $text, $size, max(0, $x), $baselineY, $color, $fontPath);
    }

    private function drawTextCenteredInBox($image, string $text, int $size, int $boxX, int $baselineY, int $boxWidth, int $color, ?string $fontPath): void
    {
        $textWidth = $this->measureTextWidth($text, $size, $fontPath);
        $x = $boxX + (int) (($boxWidth - $textWidth) / 2);
        $this->drawText($image, $text, $size, max($boxX + 10, $x), $baselineY, $color, $fontPath);
    }

    private function drawText($image, string $text, int $size, int $x, int $baselineY, int $color, ?string $fontPath, int $letterSpacing = 0): void
    {
        $text = $letterSpacing > 0 ? implode(str_repeat(' ', $letterSpacing), str_split($text)) : $text;

        if ($fontPath && function_exists('imagettftext')) {
            imagettftext($image, $size, 0, $x, $baselineY, $color, $fontPath, $text);

            return;
        }

        $font = 5;
        imagestring($image, $font, $x, $baselineY - imagefontheight($font), $text, $color);
    }

    private function measureTextWidth(string $text, int $size, ?string $fontPath): int
    {
        if ($fontPath && function_exists('imagettfbbox')) {
            $box = imagettfbbox($size, 0, $fontPath, $text);

            if ($box !== false) {
                return abs($box[2] - $box[0]);
            }
        }

        return imagefontwidth(5) * strlen($text);
    }

    private function resolveQrFontPath(bool $bold): ?string
    {
        $candidates = $bold
            ? [
                'C:\\Windows\\Fonts\\arialbd.ttf',
                'C:\\Windows\\Fonts\\ARIALBD.TTF',
                '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
                '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
            ]
            : [
                'C:\\Windows\\Fonts\\arial.ttf',
                'C:\\Windows\\Fonts\\ARIAL.TTF',
                '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
                '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function drawRoundedRectangle($image, int $x1, int $y1, int $x2, int $y2, int $radius, int $color): void
    {
        imagefilledrectangle($image, $x1 + $radius, $y1, $x2 - $radius, $y2, $color);
        imagefilledrectangle($image, $x1, $y1 + $radius, $x2, $y2 - $radius, $color);
        imagefilledellipse($image, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
    }

    private function buildQrImageFileName(Flight $flight): string
    {
        $aircraftIdentification = Str::upper(preg_replace('/[^A-Z0-9]/', '', (string) $flight->aircraft_identification));

        return 'flight-plan-qr-'.($aircraftIdentification !== '' ? $aircraftIdentification : $flight->id).'.png';
    }

    private function formatQrDate(Flight $flight): string
    {
        return $flight->date_of_flight
            ? Carbon::parse($flight->date_of_flight)->format('d M Y')
            : 'N/A';
    }

    private function formatQrTime(Flight $flight): string
    {
        $time = (string) ($flight->proposed_time ?? '');

        return $time !== '' ? $time.' Z' : 'N/A';
    }

    /**
     * Build the matched-flight payload summary used by the public and admin QR lookup flows.
     *
     * @return array<string, mixed>|null
     */
    private function buildMatchedFlightFromPayload(Request $request, string $payload): ?array
    {
        $parsedPayload = $this->qrPayloads()->parsePayload($payload);

        if ($parsedPayload === null) {
            return null;
        }

        if (($parsedPayload['format'] ?? null) === 'v2-offline') {
            $snapshot = $parsedPayload['snapshot'] ?? null;

            if (! is_array($snapshot)) {
                return null;
            }

            $flight = Flight::find((int) $parsedPayload['flight_id']);
            $status = $flight?->status instanceof FlightPlanStatus
                ? $flight->status
                : FlightPlanStatus::tryFrom((string) ($flight?->status ?? ''));
            $previewToken = $this->storeScannedFlightPlanPreview($request, [
                'payload' => $parsedPayload['normalized_payload'],
                'snapshot' => $snapshot,
                'flight_id' => $parsedPayload['flight_id'],
                'issued_at' => $parsedPayload['issued_at'],
                'key_id' => $parsedPayload['key_id'],
                'schema_id' => $parsedPayload['schema_id'],
            ]);

            return [
                'id' => (int) $parsedPayload['flight_id'],
                'aircraft_identification' => (string) ($snapshot['aircraft_identification'] ?? 'N/A'),
                'date_of_flight' => $this->formatFlightDateForLookup($snapshot['date_of_flight'] ?? null),
                'proposed_time' => UtcFourDigitTime::formatForDisplay($snapshot['proposed_time'] ?? null) ?? 'N/A',
                'departure_aerodrome' => (string) ($snapshot['departure_aerodrome'] ?? 'N/A'),
                'destination_aerodrome' => (string) ($snapshot['destination_aerodrome'] ?? 'N/A'),
                'status' => $status?->value ?? 'signed_offline_payload',
                'status_label' => $status?->label() ?? 'Signed Offline Payload',
                'status_color' => $status?->filamentColor() ?? 'info',
                'view_url' => route('flightplan.scan-qr.preview', ['token' => $previewToken]),
                'can_open' => true,
            ];
        }

        $flight = Flight::find((int) $parsedPayload['flight_id']);

        if (! $flight) {
            return null;
        }

        $status = $flight->status instanceof FlightPlanStatus
            ? $flight->status
            : FlightPlanStatus::tryFrom((string) $flight->status);

        $canOpen = Auth::check() || $this->sessionCanAccessFlight($request, $flight);

        return [
            'id' => $flight->getKey(),
            'aircraft_identification' => (string) ($flight->aircraft_identification ?? 'N/A'),
            'date_of_flight' => $this->formatFlightDateForLookup($flight->date_of_flight),
            'proposed_time' => UtcFourDigitTime::formatForDisplay($flight->proposed_time) ?? 'N/A',
            'departure_aerodrome' => (string) ($flight->departure_aerodrome ?? 'N/A'),
            'destination_aerodrome' => (string) ($flight->destination_aerodrome ?? 'N/A'),
            'status' => $status?->value ?? (string) ($flight->status ?? 'unknown'),
            'status_label' => $status?->label() ?? str((string) ($flight->status ?? 'unknown'))->headline()->toString(),
            'status_color' => $status?->filamentColor() ?? 'gray',
            'view_url' => route('flights.view', $flight),
            'can_open' => $canOpen,
        ];
    }

    /**
     * @param  array<string, mixed>  $preview
     */
    private function storeScannedFlightPlanPreview(Request $request, array $preview): string
    {
        $previewToken = (string) Str::uuid();
        $previews = $request->session()->get('scanned_flight_plan_previews', []);

        if (! is_array($previews)) {
            $previews = [];
        }

        $previews[$previewToken] = $preview;

        if (count($previews) > 10) {
            $previews = array_slice($previews, -10, null, true);
        }

        $request->session()->put('scanned_flight_plan_previews', $previews);

        return $previewToken;
    }

    private function qrPayloads(): FlightPlanQrPayloadService
    {
        return app(FlightPlanQrPayloadService::class);
    }

    private function formatFlightDateForLookup(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'N/A';
        }

        if ($value instanceof Carbon) {
            return $value->format('Y-m-d');
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function ensureReviewerAccess(): void
    {
        $user = Auth::user();

        abort_unless(
            $user
            && $user->is_active
            && $user->canReviewFlightPlans(),
            403
        );
    }

    private function ensureFlightUserAccess(): void
    {
        $user = Auth::user();

        abort_unless(
            $user
            && $user->is_active
            && $user->canViewFlightPlans(),
            403
        );
    }

    private function ensureFlightAssetAccess(Request $request, Flight $flight): void
    {
        if (Auth::check()) {
            $this->ensureFlightUserAccess();

            return;
        }

        abort_unless($this->sessionCanAccessFlight($request, $flight), 403);
    }

    private function grantSessionAccessToFlight(Request $request, Flight $flight): void
    {
        $allowedFlightIds = $request->session()->get('public_flight_access', []);

        if (! is_array($allowedFlightIds)) {
            $allowedFlightIds = [];
        }

        $allowedFlightIds[] = $flight->getKey();

        $request->session()->put(
            'public_flight_access',
            array_values(array_unique(array_map('intval', $allowedFlightIds)))
        );
    }

    private function sessionCanAccessFlight(Request $request, Flight $flight): bool
    {
        $allowedFlightIds = $request->session()->get('public_flight_access', []);

        if (! is_array($allowedFlightIds)) {
            return false;
        }

        return in_array((int) $flight->getKey(), array_map('intval', $allowedFlightIds), true);
    }

    private function resolveReviewBackUrl(Request $request, Flight $flight): string
    {
        $sessionKey = 'flight_review_back_url_'.$flight->getKey();
        $currentUrl = $request->fullUrl();
        $referer = $request->headers->get('referer');

        if (is_string($referer) && $referer !== '' && $referer !== $currentUrl && ! str_contains($referer, '/flights/'.$flight->getKey().'/view')) {
            $request->session()->put($sessionKey, $referer);

            return $referer;
        }

        $storedBackUrl = $request->session()->get($sessionKey);

        if (is_string($storedBackUrl) && $storedBackUrl !== '') {
            return $storedBackUrl;
        }

        return url('/admin');
    }

    private function resolveAtcWiresign(): string
    {
        $user = Auth::user();

        return (string) ($user?->wiresign ?: $user?->name ?: '');
    }

    /**
     * Find an already generated PDF for this flight by normalized base filename.
     */
    private function findExistingFlightPlanPdfPath(Flight $flight): ?string
    {
        $matches = $this->findStoredFlightPlanPdfPaths($flight);

        return $matches->first();
    }

    /**
     * Delete any previously stored PDFs for this flight so the accepted copy becomes the only official file.
     */
    private function deleteStoredFlightPlanPdfs(Flight $flight): void
    {
        $paths = $this->findStoredFlightPlanPdfPaths($flight);

        if ($paths->isEmpty()) {
            return;
        }

        Storage::disk('public')->delete($paths->all());
    }

    /**
     * Find all stored PDFs that belong to this flight, newest first.
     */
    private function findStoredFlightPlanPdfPaths(Flight $flight)
    {
        $baseName = $this->buildFlightPlanPdfBaseName($flight);

        if ($baseName === '') {
            return collect();
        }

        $pattern = '/\/'.preg_quote($baseName, '/').'\d{2}\.pdf$/';

        return collect(Storage::disk('public')->allFiles('flight-plans'))
            ->filter(fn (string $path) => preg_match($pattern, $path) === 1)
            ->sortByDesc(fn (string $path) => Storage::disk('public')->lastModified($path))
            ->values();
    }

    /**
     * Resolve a specific stored PDF path when an exact file name is requested.
     */
    private function resolveRequestedPdfPath(Request $request): ?string
    {
        $requestedFile = $request->query('file');

        if (! is_string($requestedFile) || $requestedFile === '') {
            return null;
        }

        $safeFileName = basename($requestedFile);

        return collect(Storage::disk('public')->allFiles('flight-plans'))
            ->first(function (string $path) use ($safeFileName) {
                return basename($path) === $safeFileName;
            });
    }

    /**
     * Resolve a unique PDF file name with a required 00-99 suffix.
     */
    private function resolveFlightPlanPdfFileName(Flight $flight, string $folderName): string
    {
        $baseName = $this->buildFlightPlanPdfBaseName($flight);

        if ($baseName === '') {
            $baseName = 'FLIGHTPLAN'.$flight->id.now('UTC')->format('YmdHi');
        }

        $directory = 'flight-plans/'.$folderName;

        for ($suffix = 0; $suffix <= 99; $suffix++) {
            $candidate = $baseName.sprintf('%02d', $suffix).'.pdf';

            if (! Storage::disk('public')->exists($directory.'/'.$candidate)) {
                return $candidate;
            }
        }

        return $baseName.now('UTC')->format('s').'.pdf';
    }

    /**
     * Build the normalized PDF base file name.
     */
    private function buildFlightPlanPdfBaseName(Flight $flight): string
    {
        $aircraftIdentification = Str::upper(preg_replace('/[^A-Z0-9]/', '', (string) $flight->aircraft_identification));
        $dateOfFlight = substr(preg_replace('/[^0-9]/', '', (string) $flight->date_of_flight), 0, 8);
        $timeDigits = preg_replace('/[^0-9]/', '', (string) $flight->proposed_time);
        $proposedTime = $timeDigits !== '' ? str_pad(substr($timeDigits, 0, 4), 4, '0', STR_PAD_LEFT) : '';

        return $aircraftIdentification.$dateOfFlight.$proposedTime;
    }
}
