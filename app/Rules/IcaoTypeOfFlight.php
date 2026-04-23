<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * ICAO Type of Flight Rule
 *
 * Validates type of flight according to ICAO Annex 3 standards.
 * Allowed values (single character):
 * - S: Scheduled air service
 * - N: Non-scheduled air transport (charter)
 * - G: General aviation
 * - M: Military
 * - X: Other
 *
 * @link https://www.icao.int/Meetings/AMC/MA/2008/Doc%209587%20-%20Manual%20of%20Air%20Navigation%20Services%20Planning.pdf
 */
class IcaoTypeOfFlight implements ValidationRule
{
    private const ALLOWED_TYPES = ['S', 'N', 'G', 'M', 'X'];

    private array $typeDescriptions = [
        'S' => 'Scheduled air service',
        'N' => 'Non-scheduled air transport (charter)',
        'G' => 'General aviation',
        'M' => 'Military',
        'X' => 'Other',
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

        if (!in_array($value, self::ALLOWED_TYPES, true)) {
            $fail('The :attribute must be one single character: ' . implode(', ', self::ALLOWED_TYPES) . ' according to ICAO standards.');
        }
    }

    /**
     * Get descriptions for type of flight codes.
     */
    public static function descriptions(): array
    {
        return [
            'S' => 'Scheduled air service',
            'N' => 'Non-scheduled air transport (charter)',
            'G' => 'General aviation',
            'M' => 'Military',
            'X' => 'Other',
        ];
    }
}
