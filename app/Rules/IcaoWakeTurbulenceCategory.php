<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * ICAO Wake Turbulence Category Rule
 *
 * Validates wake turbulence category according to ICAO Annex 3 standards.
 * Categories are based on aircraft maximum certificated takeoff weight (MTOW):
 * - L (Light): MTOW ≤ 7,000 kg
 * - M (Medium): 7,000 < MTOW ≤ 136,000 kg
 * - H (Heavy): MTOW > 136,000 kg
 * - J (Super): A380 and other specified aircraft
 *
 * @link https://www.icao.int/Meetings/AMC/MA/2008/Doc%209587%20-%20Manual%20of%20Air%20Navigation%20Services%20Planning.pdf
 */
class IcaoWakeTurbulenceCategory implements ValidationRule
{
    private const ALLOWED_CATEGORIES = ['L', 'M', 'H', 'J'];

    private array $categoryDescriptions = [
        'L' => 'Light (≤7,000 kg)',
        'M' => 'Medium (7,001-136,000 kg)',
        'H' => 'Heavy (>136,000 kg)',
        'J' => 'Super (A380, etc.)',
    ];

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $value = strtoupper(trim($value));

        if (!in_array($value, self::ALLOWED_CATEGORIES, true)) {
            $fail('The :attribute must be one of: ' . implode(', ', self::ALLOWED_CATEGORIES) . ' according to ICAO standards.');
        }
    }

    /**
     * Get descriptions for wake turbulence categories.
     */
    public static function descriptions(): array
    {
        return [
            'L' => 'Light (≤7,000 kg)',
            'M' => 'Medium (7,001-136,000 kg)',
            'H' => 'Heavy (>136,000 kg)',
            'J' => 'Super (A380, etc.)',
        ];
    }
}
