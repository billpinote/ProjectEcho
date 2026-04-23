<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * ICAO Flight Level Rule
 *
 * Validates flight level (altitude) according to ICAO Annex 3 standards.
 * 
 * Supported Formats:
 * - F + 3 digits: Flight level in hundreds of feet (e.g., F100 = 10,000 ft, F330 = 33,000 ft)
 * - S + 4 digits: Metric flight level in meters (e.g., S1130 = 11,300 m)
 * - A + 3-4 digits: Specific altitude in hundreds of feet (e.g., A045 = 4,500 ft)
 * - VFR: Visual Flight Rules (no specific altitude)
 *
 * @link https://www.icao.int/Meetings/AMC/MA/2008/Doc%209587%20-%20Manual%20of%20Air%20Navigation%20Services%20Planning.pdf
 */
class IcaoFlightLevel implements ValidationRule
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

        // Allow VFR for Visual Flight Rules
        if ($value === 'VFR') {
            return;
        }

        // Pattern: F/S/A followed by appropriate digits
        // F + 3 digits: Flight level in hundreds of feet (F100-F450)
        // S + 4 digits: Metric flight level in meters (S1130, etc)
        // A + 3-4 digits: Altitude in hundreds of feet (A045, A0100)
        $pattern = '/^(F\d{3}|S\d{4}|A\d{3,4}|VFR)$/';

        if (!preg_match($pattern, $value)) {
            $fail('The :attribute must be in ICAO format. Examples: F100 (flight level), A045 (altitude in feet), S1130 (altitude in meters), or VFR.');
        }

        // Validate reasonable ranges
        if (str_starts_with($value, 'F')) {
            $level = (int) substr($value, 1);
            if ($level < 100 || $level > 450) {
                $fail('The :attribute flight level must be between F100 and F450.');
            }
        } elseif (str_starts_with($value, 'A')) {
            $altitude = (int) substr($value, 1);
            if ($altitude < 1 || $altitude > 9999) {
                $fail('The :attribute altitude must be between A001 and A9999 (in hundreds of feet).');
            }
        }
    }
}
