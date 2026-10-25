<?php

namespace App\Domain\FlightPlans\Services;

use App\Models\Flight;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Carbon\Carbon;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class FlightPlanQrRenderer
{
    public function __construct(
        private FlightPlanQrPayloadService $payloads,
    ) {}

    public function generateFlightPlanQrCodeBase64(Flight $flight, int $size = 250, int $margin = 2): ?string
    {
        $payload = $this->payloads->buildPayload($flight);

        return $payload !== null
            ? $this->generateQrCodeBase64FromPayload($payload, $size, $margin)
            : null;
    }

    public function generateQrCodeBase64FromPayload(string $payload, int $size = 250, int $margin = 2): string
    {
        $qrCodeSvg = QrCode::size($size)->margin($margin)->format('svg')->generate($payload);

        return 'data:image/svg+xml;base64,'.base64_encode($qrCodeSvg);
    }

    public function generateFlightPlanQrCardPng(Flight $flight): string
    {
        $payload = $this->payloads->buildPayload($flight);

        if ($payload === null) {
            abort(404, 'QR payload is not available for this flight plan.');
        }

        $width = 1080;
        $height = 1350;
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

        $this->drawCenteredText($image, 'ECHO · FLIGHT PLAN', 22, 150, $accent, $boldFont, 2);
        $this->drawCenteredText($image, 'FLIGHT PLAN READY', 26, 215, $accent, $boldFont);
        $this->drawCenteredText($image, strtoupper((string) ($flight->aircraft_identification ?? 'N/A')), 44, 300, $ink, $boldFont);
        $this->drawCenteredText($image, strtoupper(sprintf(
            '%s → %s',
            (string) ($flight->departure_aerodrome ?? 'N/A'),
            (string) ($flight->destination_aerodrome ?? 'N/A'),
        )), 28, 355, $ink, $regularFont);
        $this->drawCenteredText($image, strtoupper($this->formatQrDate($flight).' · '.$this->formatQrTime($flight)), 24, 405, $muted, $regularFont);

        $qrOuterX = 180;
        $qrOuterY = 455;
        $qrOuterSize = 720;
        $this->drawRoundedRectangle($image, $qrOuterX, $qrOuterY, $qrOuterX + $qrOuterSize, $qrOuterY + $qrOuterSize, 34, $soft);
        imagefilledrectangle($image, $qrOuterX + 42, $qrOuterY + 42, $qrOuterX + $qrOuterSize - 42, $qrOuterY + $qrOuterSize - 42, $white);
        $this->drawQrCode($image, $payload, $qrOuterX + 78, $qrOuterY + 78, $qrOuterSize - 156, 4, $black, $white);

        $this->drawCenteredText($image, 'PRESENT TO ATC FOR PROCESSING', 24, 1235, $accent, $boldFont);
        $referenceDate = $flight->date_of_flight ? Carbon::parse($flight->date_of_flight)->format('md') : '----';
        $this->drawCenteredText($image, sprintf(
            'REV %d · %s-%s',
            (int) ($flight->revision_number ?? 1),
            strtoupper((string) ($flight->aircraft_identification ?? 'FLIGHT')),
            $referenceDate,
        ), 16, 1285, $muted, $regularFont);

        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return $png;
    }

    public function ensureGdExtensionIsLoaded(): void
    {
        if (extension_loaded('gd') && function_exists('imagecreatetruecolor')) {
            return;
        }

        abort(500, 'QR image downloads require the PHP GD extension. Enable extension=gd in php.ini and restart the web server.');
    }

    private function drawQrCode(\GdImage $image, string $payload, int $x, int $y, int $targetSize, int $quietZone, int $black, int $white): void
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

    private function drawMetaBox(\GdImage $image, int $x, int $y, int $width, int $height, string $label, string $value, int $background, int $labelColor, int $valueColor, ?string $regularFont, ?string $boldFont): void
    {
        $this->drawRoundedRectangle($image, $x, $y, $x + $width, $y + $height, 24, $background);
        $this->drawText($image, $label, 16, $x + 28, $y + 38, $labelColor, $boldFont, 2);
        $this->drawTextCenteredInBox($image, strtoupper($value), 24, $x, $y + 84, $width, $valueColor, $boldFont);
    }

    private function drawCenteredText(\GdImage $image, string $text, int $size, int $baselineY, int $color, ?string $fontPath, int $letterSpacing = 0): void
    {
        $text = $letterSpacing > 0 ? implode(str_repeat(' ', $letterSpacing), str_split($text)) : $text;
        $textWidth = $this->measureTextWidth($text, $size, $fontPath);
        $x = (int) ((imagesx($image) - $textWidth) / 2);
        $this->drawText($image, $text, $size, max(0, $x), $baselineY, $color, $fontPath);
    }

    private function drawTextCenteredInBox(\GdImage $image, string $text, int $size, int $boxX, int $baselineY, int $boxWidth, int $color, ?string $fontPath): void
    {
        $textWidth = $this->measureTextWidth($text, $size, $fontPath);
        $x = $boxX + (int) (($boxWidth - $textWidth) / 2);
        $this->drawText($image, $text, $size, max($boxX + 10, $x), $baselineY, $color, $fontPath);
    }

    private function drawText(\GdImage $image, string $text, int $size, int $x, int $baselineY, int $color, ?string $fontPath, int $letterSpacing = 0): void
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

    private function drawRoundedRectangle(\GdImage $image, int $x1, int $y1, int $x2, int $y2, int $radius, int $color): void
    {
        imagefilledrectangle($image, $x1 + $radius, $y1, $x2 - $radius, $y2, $color);
        imagefilledrectangle($image, $x1, $y1 + $radius, $x2, $y2 - $radius, $color);
        imagefilledellipse($image, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
    }

    public function buildQrImageFileName(Flight $flight): string
    {
        $aircraftIdentification = Str::upper(preg_replace('/[^A-Z0-9]/', '', (string) $flight->aircraft_identification));
        $date = $flight->date_of_flight
            ? Carbon::parse($flight->date_of_flight)->format('Y-m-d')
            : 'undated';

        return 'ECHO-'.($aircraftIdentification !== '' ? $aircraftIdentification : 'FLIGHT-'.$flight->id).'-'.$date.'.png';
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
}
