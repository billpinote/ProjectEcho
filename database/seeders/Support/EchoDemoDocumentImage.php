<?php

namespace Database\Seeders\Support;

use RuntimeException;

class EchoDemoDocumentImage
{
    public function render(string $name, string $credential, string $type, string $issued, string $expires, string $details): string
    {
        $font = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf');
        if (! extension_loaded('gd') || ! function_exists('imagettftext') || ! is_file($font)) {
            throw new RuntimeException('Demo documents require PHP GD with FreeType and the installed DomPDF DejaVuSans font.');
        }

        $image = imagecreatetruecolor(1100, 680);
        $background = imagecolorallocate($image, 243, 246, 250);
        $navy = imagecolorallocate($image, 25, 43, 65);
        $muted = imagecolorallocate($image, 75, 90, 108);
        $red = imagecolorallocate($image, 166, 27, 39);
        $pink = imagecolorallocate($image, 255, 228, 230);
        imagefill($image, 0, 0, $background);
        imagefilledrectangle($image, 0, 0, 1100, 94, $navy);
        $white = imagecolorallocate($image, 255, 255, 255);

        try {
            imagettftext($image, 24, 0, 44, 60, $white, $font, 'PROJECT ECHO TEST DATA');
            imagettftext($image, 23, 0, 44, 146, $navy, $font, $type);
            imagettftext($image, 29, 0, 44, 212, $navy, $font, $name);
            imagettftext($image, 19, 0, 44, 263, $muted, $font, 'Fictional credential: '.$credential);
            imagettftext($image, 18, 0, 44, 310, $muted, $font, 'Issued: '.$issued.'     Expires: '.$expires);
            imagettftext($image, 18, 0, 44, 357, $muted, $font, $details);
            imagefilledrectangle($image, 24, 400, 1076, 516, $pink);
            imagettftext($image, 47, 0, 103, 478, $red, $font, 'SAMPLE — NOT VALID');
            imagettftext($image, 17, 0, 44, 563, $muted, $font, 'Fictional fixture. No government affiliation or regulatory authority.');
            imagettftext($image, 16, 0, 44, 636, $red, $font, 'FICTIONAL DOCUMENT FOR SOFTWARE TESTING ONLY');

            ob_start();
            try {
                if (! imagepng($image)) {
                    throw new RuntimeException('Could not render the demo document PNG.');
                }

                return (string) ob_get_contents();
            } finally {
                ob_end_clean();
            }
        } finally {
            imagedestroy($image);
        }
    }
}
