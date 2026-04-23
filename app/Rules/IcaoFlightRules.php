<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * ICAO Flight Rules Rule (Item 8)
 *
 * Validates flight rules according to ICAO Annex 3 standards.
 * Single-character codes:
 * - I: IFR - Instrument Flight Rules (entire flight under IFR)
 * - V: VFR - Visual Flight Rules (entire flight under VFR)
 * - Y: IFR first, then VFR (mixed flight rules, IFR to VFR transition)
 * - Z: VFR first, then IFR (mixed flight rules, VFR to IFR transition)
 *
 * @link https://www.icao.int/Meetings/AMC/MA/2008/Doc%209587%20-%20Manual%20of%20Air%20Navigation%20Services%20Planning.pdf
 */
class IcaoFlightRules implements ValidationRule
{
    private const ALLOWED_RULES = ['I', 'V', 'Y', 'Z'];

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

        if (!in_array($value, self::ALLOWED_RULES, true)) {
            $fail('The :attribute must be one of: I (IFR), V (VFR), Y (IFR then VFR), Z (VFR then IFR) according to ICAO Item 8.');
        }
    }
}
