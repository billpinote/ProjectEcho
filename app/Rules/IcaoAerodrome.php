<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * ICAO Aerodrome Code Rule
 *
 * Validates aerodrome codes according to ICAO Annex 3 standards.
 * Format:
 * - 4-character ICAO code (A-Z only): KJFK, LFPG, EGLL, RJTT, SBGR, etc.
 * - Special code ZZZZ: Used when aerodrome is not in the ICAO registry
 *
 * @link https://www.icao.int/Meetings/AMC/MA/2008/Doc%209587%20-%20Manual%20of%20Air%20Navigation%20Services%20Planning.pdf
 */
class IcaoAerodrome implements ValidationRule
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

        // Must be exactly 4 characters, all letters, or the special code ZZZZ
        if (strlen($value) !== 4 || !preg_match('/^[A-Z]{4}$/', $value)) {
            $fail('The :attribute must be a valid 4-character ICAO aerodrome code (e.g., KJFK, LFPG) or ZZZZ for unknown aerodromes.');
        }
    }
}
