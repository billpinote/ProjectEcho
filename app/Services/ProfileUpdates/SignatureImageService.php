<?php

namespace App\Services\ProfileUpdates;

use Illuminate\Validation\ValidationException;

class SignatureImageService
{
    public const MAX_BYTES = 2097152;

    /** @return array{bytes: string, width: int, height: int, sha256: string, source: string} */
    public function prepare(string $bytes, string $source): array
    {
        if (! in_array($source, ['draw', 'upload'], true) || strlen($bytes) > self::MAX_BYTES) {
            $this->invalid('Use Draw Signature or upload a PNG/JPEG image no larger than 2 MB.');
        }
        $dimensions = @getimagesizefromstring($bytes);
        if ($dimensions === false || ! in_array($dimensions[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)) {
            $this->invalid('The signature must contain a valid PNG or JPEG image.');
        }
        [$width, $height] = $dimensions;
        if ($width < 32 || $height < 16 || $width > 4096 || $height > 2048 || $width * $height > 4194304) {
            $this->invalid('Use an image from 32 × 16 to 4096 × 2048 pixels, with no more than 4 megapixels.');
        }
        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            $this->invalid('The signature image could not be decoded.');
        }
        imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $left = $width;
        $right = -1;
        $top = $height;
        $bottom = -1;
        $ink = 0;
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $color = imagecolorat($image, $x, $y);
                $alpha = ($color >> 24) & 127;
                $red = ($color >> 16) & 255;
                $green = ($color >> 8) & 255;
                $blue = $color & 255;
                if ($alpha >= 120 || min($red, $green, $blue) >= 245) {
                    imagesetpixel($image, $x, $y, $transparent);

                    continue;
                }
                $ink++;
                $left = min($left, $x);
                $right = max($right, $x);
                $top = min($top, $y);
                $bottom = max($bottom, $y);
                if ($source === 'draw') {
                    imagesetpixel($image, $x, $y, ($alpha << 24));
                }
            }
        }
        if ($ink < 20 || $right - $left < 10 || $bottom - $top < 3) {
            imagedestroy($image);
            $this->invalid('Draw or upload a visible signature; blank images and isolated marks cannot be submitted.');
        }
        $contentWidth = $right - $left + 1;
        $contentHeight = $bottom - $top + 1;
        for ($margin = 8; $margin >= 0; $margin--) {
            $croppedWidth = min(4096, max(32, $contentWidth + $margin * 2));
            $croppedHeight = min(2048, max(16, $contentHeight + $margin * 2));
            if ($croppedWidth * $croppedHeight <= 4194304) {
                break;
            }
        }
        $cropped = imagecreatetruecolor($croppedWidth, $croppedHeight);
        imagealphablending($cropped, false);
        imagesavealpha($cropped, true);
        imagefill($cropped, 0, 0, imagecolorallocatealpha($cropped, 0, 0, 0, 127));
        imagecopy($cropped, $image, min(8, intdiv($croppedWidth - $contentWidth, 2)), min(8, intdiv($croppedHeight - $contentHeight, 2)), $left, $top, $contentWidth, $contentHeight);
        imagedestroy($image);
        unset($image);
        ob_start();
        imagepng($cropped);
        $normalized = ob_get_clean();
        imagedestroy($cropped);

        return ['bytes' => $normalized, 'width' => $croppedWidth, 'height' => $croppedHeight, 'sha256' => hash('sha256', $normalized), 'source' => $source];
    }

    /** @return array{bytes: string, width: int, height: int, sha256: string, source: string} */
    public function fromSubmission(string $submission): array
    {
        if (strlen($submission) > 3000000) {
            $this->invalid('The signature submission is too large.');
        }
        $input = json_decode($submission, true);
        if (! is_array($input) || ! is_string($input['source'] ?? null) || ! is_string($input['data_url'] ?? null)
            || ! preg_match('/^data:image\/(?:png|jpeg);base64,(.+)$/s', $input['data_url'], $matches)) {
            $this->invalid('Prepare a signature preview before submitting.');
        }
        $bytes = base64_decode($matches[1], true);
        if ($bytes === false) {
            $this->invalid('The signature image is invalid.');
        }

        return $this->prepare($bytes, (string) ($input['source'] ?? ''));
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['data.signature_submission' => $message]);
    }
}
