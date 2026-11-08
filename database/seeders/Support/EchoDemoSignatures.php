<?php

namespace Database\Seeders\Support;

use App\Models\User;
use App\Services\ProfileUpdates\ProfileUpdateRequestService;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class EchoDemoSignatures
{
    /** @var list<string> */
    private array $createdPaths = [];

    public function __construct(private readonly ProfileUpdateRequestService $requests) {}

    public function create(User $user, User $reviewer): void
    {
        // Preserve every existing version, including user-submitted pending signatures.
        if (! $user->isPilot() || $user->signatureVersions()->exists()) {
            return;
        }

        $request = $this->requests->submit($user, [],
            'PROJECT ECHO TEST DATA: synthetic signature fixture, not a real handwritten signature.',
            signatureSubmission: json_encode([
                'source' => 'upload', 'data_url' => 'data:image/png;base64,'.base64_encode($this->render($user)),
            ], JSON_THROW_ON_ERROR));
        $this->createdPaths[] = $request->signature->stored_path;
        $this->requests->approve($request, $reviewer,
            'Development fixture auto-approved by EchoDemoSeeder. SYNTHETIC / DEMO ONLY.');
    }

    private function render(User $user): string
    {
        $font = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Oblique.ttf');
        if (! extension_loaded('gd') || ! function_exists('imagettftext') || ! is_file($font)) {
            throw new RuntimeException('Demo signatures require GD with FreeType and the DomPDF DejaVuSans oblique font.');
        }
        $image = imagecreatetruecolor(660, 160);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        $ink = imagecolorallocate($image, 25, 43, 65);
        try {
            imagettftext($image, 27, 3, 20, 73, $ink, $font, $user->fullName());
            imagesetthickness($image, 2);
            $offset = hexdec(substr(hash('sha256', $user->email), 0, 2)) % 12;
            for ($x = 25; $x < 430; $x++) {
                imageline($image, $x, 92 + (int) (7 * sin(($x + $offset) / 45)),
                    $x + 1, 92 + (int) (7 * sin(($x + 1 + $offset) / 45)), $ink);
            }
            imagestring($image, 5, 20, 126, 'SYNTHETIC SIGNATURE - DEMO ONLY', $ink);
            ob_start();
            try {
                if (! imagepng($image)) {
                    throw new RuntimeException('Could not render demo signature.');
                }

                return (string) ob_get_contents();
            } finally {
                ob_end_clean();
            }
        } finally {
            imagedestroy($image);
        }
    }

    public function cleanUpFailedRun(): void
    {
        foreach ($this->createdPaths as $path) {
            Storage::disk('local')->delete($path);
        }
        $this->finishRun();
    }

    public function finishRun(): void
    {
        $this->createdPaths = [];
    }
}
