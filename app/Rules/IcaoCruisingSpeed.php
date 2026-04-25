<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * ICAO Cruising Speed Rule
 *
 * Validates cruising speed format according to ICAO Annex 3 standards.
 * Format: [Unit][Speed]
 * - N: Knots (nautical miles per hour)
 * - M: Mach number (subsonic, decimal format like M0.80)
 * - K: Kilometers per hour
 *
 * Examples: N450, M0.80, K900, N0500
 *
 * @link https://www.icao.int/Meetings/AMC/MA/2008/Doc%209587%20-%20Manual%20of%20Air%20Navigation%20Services%20Planning.pdf
 */
class IcaoCruisingSpeed implements ValidationRule
{
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

        // Knots/kph use 3-4 digits; Mach uses decimal notation such as M0.80.
        $pattern = '/^(?:[NK]\d{3,4}|M\d{1,2}\.\d{1,2})$/';

        if (!preg_match($pattern, $value)) {
            $fail('The :attribute must be in ICAO format: N[speed in knots], M[Mach number], or K[speed in km/h]. Examples: N450, M0.80, K900.');
        }

        // Additional validation for Mach numbers
        if (str_starts_with($value, 'M')) {
            $mach = (float) substr($value, 1);
            if ($mach < 0.1 || $mach > 2.0) {
                $fail('The :attribute Mach number must be between 0.1 and 2.0.');
            }
        }
    }
}
