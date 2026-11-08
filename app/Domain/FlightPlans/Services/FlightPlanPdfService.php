<?php

namespace App\Domain\FlightPlans\Services;

use App\Domain\FlightPlans\Support\FilingDocumentRegistry;
use App\Models\Flight;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FlightPlanPdfService
{
    public function __construct(private FlightPlanQrRenderer $qrRenderer) {}

    public function regenerate(Flight $flight): string
    {
        $this->deleteExisting($flight);

        $folderName = now('UTC')->format('Ymd');
        $fileName = $this->uniqueFileName($flight, $folderName);
        $storagePath = $this->privateDirectory($flight).'/'.$fileName;
        Storage::disk('local')->put($storagePath, $this->renderSnapshot($flight, app(FilingAuthorizationService::class)->signatureBytes($flight)), ['visibility' => 'private']);

        return $storagePath;
    }

    /** @return Collection<int, string> */
    public function storedPaths(Flight $flight): Collection
    {
        $private = collect(Storage::disk('local')->files($this->privateDirectory($flight)))->sort()->values();
        if ($private->isNotEmpty() || $flight->filingAuthorizations()->exists()) {
            return $private;
        }
        $baseName = $this->baseName($flight);

        if ($baseName === '') {
            return collect();
        }

        $pattern = '/\/'.preg_quote($baseName, '/').'\d{2}\.pdf$/';

        return collect(Storage::disk('public')->allFiles('flight-plans'))
            ->filter(fn (string $path) => preg_match($pattern, $path) === 1)
            ->sortByDesc(fn (string $path) => Storage::disk('public')->lastModified($path))
            ->values();
    }

    public function deleteExisting(Flight $flight): void
    {
        $paths = $this->storedPaths($flight);

        if ($paths->isNotEmpty()) {
            foreach ($paths as $path) {
                Storage::disk($this->diskFor($path))->delete($path);
            }
        }
    }

    private function uniqueFileName(Flight $flight, string $folderName): string
    {
        $baseName = $this->baseName($flight);

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

    private function baseName(Flight $flight): string
    {
        $aircraftIdentification = Str::upper(preg_replace('/[^A-Z0-9]/', '', (string) $flight->aircraft_identification));
        $dateOfFlight = substr(preg_replace('/[^0-9]/', '', (string) $flight->date_of_flight), 0, 8);
        $timeDigits = preg_replace('/[^0-9]/', '', (string) $flight->proposed_time);
        $proposedTime = $timeDigits !== '' ? str_pad(substr($timeDigits, 0, 4), 4, '0', STR_PAD_LEFT) : '';

        return $aircraftIdentification.$dateOfFlight.$proposedTime;
    }

    public function resolveRequestedPath(mixed $requestedFile, Flight $flight): ?string
    {

        if (! is_string($requestedFile) || $requestedFile === '') {
            return null;
        }

        $safeFileName = basename($requestedFile);

        return $this->storedPaths($flight)
            ->first(function (string $path) use ($safeFileName) {
                return basename($path) === $safeFileName;
            });
    }

    public function buildPdfOnlyFileName(Flight $flight): string
    {
        $baseName = $this->baseName($flight);

        if ($baseName === '') {
            $baseName = 'FLIGHTPLAN'.now('UTC')->format('YmdHi');
        }

        return $baseName.'-PDF-ONLY.pdf';
    }

    public function renderPdfOnly(Flight $flight): string
    {
        $pdf = Pdf::loadView('flightplan.pdf', [
            'flight' => $flight,
            'qrCodeBase64' => null,
            'isPdfOnly' => true,
            'pilotSignatureDataUrl' => null,
        ])->setPaper('a4', 'portrait');

        return $pdf->output();
    }

    public function renderSnapshot(Flight $flight, ?string $signatureBytes = null, bool $includeQr = true): string
    {
        return Pdf::loadView('flightplan.pdf', [
            'flight' => $flight,
            'qrCodeBase64' => $includeQr ? $this->qrRenderer->generateFlightPlanQrCodeBase64($flight) : null,
            'pilotSignatureDataUrl' => $signatureBytes === null ? null : 'data:image/png;base64,'.base64_encode($signatureBytes),
        ])->setPaper('a4', 'portrait')->output();
    }

    public function diskFor(string $path): string
    {
        return str_starts_with($path, 'filings/') ? 'local' : 'public';
    }

    private function privateDirectory(Flight $flight): string
    {
        $fingerprint = FilingDocumentRegistry::fingerprint(
            app(FilingDocumentRegistry::class)->snapshots($flight),
        );
        $package = $flight->filingAuthorizations()->latest('id')->value('id') ?? 'unsigned';

        return 'filings/'.$flight->getKey().'/'.$package.'-'.$fingerprint;
    }
}
