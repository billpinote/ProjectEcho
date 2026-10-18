<?php

namespace App\Domain\FlightPlans\Services;

use App\Domain\FlightPlans\Support\FlightManifestData;
use App\Domain\FlightPlans\Support\ManifestPdfLayout;
use App\Models\Flight;
use App\Models\FlightManifest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Crypt;
use setasign\Fpdi\Tcpdf\Fpdi;

class ManifestPdfService
{
    public function templatePath(): string
    {
        return resource_path('pdf/templates/CAAP Dom FPL Manifest form.pdf');
    }

    /** @return array<string, mixed> */
    public function dataFor(Flight $flight, FlightManifest $manifest): array
    {
        return array_replace(app(FlightManifestData::class)->fromFlight($flight), $manifest->toArray(), [
            'coa_expiry_date' => $manifest->coa_expiry_date?->format('Y-m-d'),
            'persons' => $manifest->persons->map->only(['full_name', 'nationality', 'classification'])->all(),
        ]);
    }

    public function forFlight(Flight $flight, FlightManifest $manifest): string
    {
        if ($flight->hasBeenAccepted()) {
            abort_if($manifest->accepted_pdf_content === null, 409, 'This legacy accepted filing has no preserved PDF. It cannot be regenerated as an original.');
            $bytes = base64_decode(Crypt::decryptString($manifest->accepted_pdf_content), true);
            abort_unless(is_string($bytes) && hash_equals($manifest->accepted_pdf_sha256, hash('sha256', $bytes)), 500, 'Preserved PDF integrity check failed.');

            return $bytes;
        }

        return $this->render($this->dataFor($flight, $manifest));
    }

    public function snapshot(Flight $flight): void
    {
        $manifest = $flight->manifest()->with('persons')->firstOrFail();
        $bytes = $this->render($this->dataFor($flight, $manifest));
        $manifest->forceFill([
            'accepted_pdf_content' => Crypt::encryptString(base64_encode($bytes)),
            'accepted_pdf_sha256' => hash('sha256', $bytes),
            'accepted_pdf_template_sha256' => hash_file('sha256', $this->templatePath()),
            'accepted_pdf_created_at' => now(),
        ])->save();
    }

    /** @param array<string, mixed> $data */
    public function render(array $data): string
    {
        $pdf = new Fpdi('P', 'pt', [612, 792], true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->setFontSubsetting(true);
        $pdf->setCellPaddings(0, 0, 0, 0);
        $pdf->setCellHeightRatio(1.05);
        $pdf->SetCreator('Project Echo');
        $pdf->SetTitle('Domestic Flight Manifest');
        if ($pdf->setSourceFile($this->templatePath()) !== 2) {
            throw new \RuntimeException('The official manifest template must contain two pages.');
        }
        $first = $pdf->importPage(1);
        $instructions = $pdf->importPage(2);
        foreach ([$first, $instructions] as $page) {
            $size = $pdf->getTemplateSize($page);
            if (abs($size['width'] - 612) > 0.01 || abs($size['height'] - 792) > 0.01) {
                throw new \RuntimeException('The official template must retain its native US Letter dimensions.');
            }
        }
        $pdf->AddPage('P', [612, 792]);
        $pdf->useTemplate($first, 0, 0, 612, 792);
        $data['owner_and_phone'] = implode("\n", array_filter([$data['aircraft_owner_name'] ?? '', $data['aircraft_owner_contact_number'] ?? ''], fn ($value): bool => filled($value)));
        $data['credentials'] = implode("\n", array_filter([$data['pilot_license_no'] ?? '', $data['pilot_ratings'] ?? '', $data['license_expiry_date'] ?? ''], fn ($value): bool => filled($value)));
        $overflow = [];
        foreach (ManifestPdfLayout::FIELDS as $key => $box) {
            $this->field($pdf, (string) ($data[$key] ?? ''), $box, $key, $overflow);
        }
        $persons = array_values($data['persons'] ?? []);
        foreach (array_slice($persons, 0, 25) as $index => $person) {
            $y = ManifestPdfLayout::ROW_TOPS[$index] + 1;
            $this->field($pdf, $person['full_name'], [85, $y, 286, 12], 'Person '.($index + 1).' name', $overflow);
            $this->field($pdf, (string) ($person['nationality'] ?? ''), [382, $y, 194, 12], 'Person '.($index + 1).' nationality', $overflow);
        }
        foreach (array_chunk(array_slice($persons, 25), 25) as $sheet => $remaining) {
            $tableTop = $this->supplementHeader($pdf, $data, 'CREW / PASSENGER CONTINUATION '.($sheet + 1));
            $pdf->SetFont('dejavusans', '', 9);
            $pdf->Text(49, $tableTop, 'No.');
            $pdf->Text(85, $tableTop, 'Name of passenger / crew');
            $pdf->Text(382, $tableTop, 'Nationality');
            foreach ($remaining as $index => $person) {
                $number = 26 + $sheet * 25 + $index;
                $y = $tableTop + 21 + $index * 22;
                $pdf->Line(44, $y + 21, 582, $y + 21);
                $pdf->Text(49, $y + 5, (string) $number);
                $this->field($pdf, $person['full_name'], [85, $y, 286, 20], 'Person '.$number.' name', $overflow);
                $this->field($pdf, (string) ($person['nationality'] ?? ''), [382, $y, 194, 20], 'Person '.$number.' nationality', $overflow);
            }
        }
        if ($overflow !== []) {
            $y = $this->supplementHeader($pdf, $data, 'FULL FIELD VALUES');
            foreach ($overflow as $label => $value) {
                $pdf->SetFont('dejavusans', '', 9);
                $text = $label.': '.$value;
                $height = $pdf->getStringHeight(520, $text);
                if ($y + $height > 735) {
                    $y = $this->supplementHeader($pdf, $data, 'FULL FIELD VALUES (CONTINUED)');
                }
                $pdf->MultiCell(520, $height, $text, 0, 'L', false, 1, 49, $y);
                $y += $height + 10;
            }
        }
        $pdf->AddPage('P', [612, 792]);
        $pdf->useTemplate($instructions, 0, 0, 612, 792);

        return $pdf->Output('', 'S');
    }

    /**
     * @param  array{0: int|float, 1: int|float, 2: int|float, 3: int|float}  $box
     * @param  array<string, string>  $overflow
     */
    private function field(Fpdi $pdf, string $value, array $box, string $label, array &$overflow): void
    {
        if ($value === '') {
            return;
        }
        $pdf->SetFont('dejavusans', '', 10);
        foreach (mb_str_split($value) as $character) {
            $codepoint = mb_ord($character);
            if (! in_array($codepoint, [10, 13], true) && ($codepoint < 32 || ! $pdf->isCharDefined($codepoint))) {
                throw new HttpResponseException(response()->json([
                    'message' => 'A manifest value contains characters unavailable in the PDF font. Use a supported spelling before generating the PDF.',
                    'errors' => [$label => ['Unsupported PDF characters. No PDF was generated.']],
                ], 422)->header('Cache-Control', 'no-store'));
            }
        }
        [$x, $y, $width, $height] = $box;
        for ($size = 10; $size >= 7; $size -= 0.5) {
            $pdf->SetFont('dejavusans', '', $size);
            if ($pdf->getStringHeight($width, $value) <= $height) {
                $pdf->MultiCell($width, $height, $value, 0, 'L', false, 1, $x, $y, true, 0, false, true, $height, 'M');

                return;
            }
        }
        $overflow[$label] = $value;
        $pdf->SetFont('dejavusans', '', 7);
        $pdf->MultiCell($width, $height, 'See full field values supplement', 0, 'L', false, 1, $x, $y);
    }

    /** @param array<string, mixed> $data */
    private function supplementHeader(Fpdi $pdf, array $data, string $title): float
    {
        $pdf->AddPage('P', [612, 792]);
        $pdf->SetFont('dejavusans', 'B', 11);
        $pdf->Text(49, 40, 'ECHO MANIFEST SUPPLEMENT - '.$title);
        $pdf->SetFont('dejavusans', '', 9);
        $text = 'Supplement to CAAP Domestic Flight Manifest (not an official CAAP form).'."\n".'Aircraft: '.($data['aircraft_identification'] ?? '').' | Date: '.($data['date_of_flight'] ?? '');
        $height = max(40, $pdf->getStringHeight(520, $text));
        $pdf->MultiCell(520, $height, $text, 0, 'L', false, 1, 49, 62);

        return max(115, 62 + $height + 12);
    }
}
