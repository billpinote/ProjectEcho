<?php

namespace App\Domain\FlightPlans\Services;

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
        $storagePath = 'flight-plans/'.$folderName.'/'.$fileName;
        $qrCodeBase64 = $this->qrRenderer->generateFlightPlanQrCodeBase64($flight);

        $pdf = Pdf::loadView('flightplan.pdf', [
            'flight' => $flight,
            'qrCodeBase64' => $qrCodeBase64,
        ])->setPaper('a4', 'portrait');

        Storage::disk('public')->put($storagePath, $pdf->output());

        return $storagePath;
    }

    /** @return Collection<int, string> */
    public function storedPaths(Flight $flight): Collection
    {
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
            Storage::disk('public')->delete($paths->all());
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

    public function resolveRequestedPath(mixed $requestedFile): ?string
    {

        if (! is_string($requestedFile) || $requestedFile === '') {
            return null;
        }

        $safeFileName = basename($requestedFile);

        return collect(Storage::disk('public')->allFiles('flight-plans'))
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
        ])->setPaper('a4', 'portrait');

        return $pdf->output();
    }
}
