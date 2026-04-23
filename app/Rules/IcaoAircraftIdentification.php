<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * ICAO Aircraft Identification Rule
 *
 * Validates aircraft identification according to ICAO Annex 3 standards.
 * Format: 2-7 alphanumeric characters (A-Z, 0-9)
 * Examples: N12345, GXABC, 5YTJK, C747, HB
 *
 * @link https://www.icao.int/Meetings/AMC/MA/2008/Doc%209587%20-%20Manual%20of%20Air%20Navigation%20Services%20Planning.pdf
 */
class IcaoAircraftIdentification implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            // Aircraft identification is typically required - let required rule handle this
            return;
        }

        $value = strtoupper(trim($value));

        // ICAO allows 2-7 alphanumeric characters (A-Z, 0-9)
        // Hyphens are present in some registration formats but not in ICAO FPL
        $pattern = '/^[A-Z0-9]{2,7}$/';

        if (!preg_match($pattern, $value)) {
            $fail('The :attribute must contain 2-7 alphanumeric characters (A-Z, 0-9) according to ICAO standards.');
        }
    }
}
