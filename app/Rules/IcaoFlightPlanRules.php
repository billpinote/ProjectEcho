<?php

namespace App\Rules;

/**
 * ICAO Flight Plan Rules Documentation
 *
 * This class provides a central reference for all ICAO flight plan validation rules.
 * It documents the standards from ICAO Annex 3 (Meteorological Service for International Air Navigation)
 * and ICAO Doc 4444 (Air Traffic Management Procedures).
 *
 * Key ICAO FPL Format Rules:
 * ===========================
 *
 * 1. AIRCRAFT IDENTIFICATION (Field 7)
 *    - Format: 1-7 alphanumeric characters (A-Z, 0-9)
 *    - Examples: N12345, GXABC, LFPG1, 5YTJK
 *    - Rule: IcaoAircraftIdentification
 *
 * 2. FLIGHT RULES (Field 8)
 *    - Format: Single character
 *    - I: IFR - Instrument Flight Rules (entire flight under IFR)
 *    - V: VFR - Visual Flight Rules (entire flight under VFR)
 *    - Y: IFR first, then VFR (mixed flight rules, IFR to VFR transition)
 *    - Z: VFR first, then IFR (mixed flight rules, VFR to IFR transition)
 *    - Rule: IcaoFlightRules
 *
 * 3. TYPE OF FLIGHT (Field 8)
 *    - Format: Single character
 *    - S: Scheduled air service
 *    - G: General aviation (non-scheduled)
 *    - M: Military
 *    - C: Charter/Non-scheduled commercial
 *    - P: Positioning flight (no passengers/cargo)
 *    - T: Test flight
 *    - X: Other
 *    - Rule: IcaoTypeOfFlight
 *
 * 4. AIRCRAFT TYPE (Field 9)
 *    - Format: 2-4 character ICAO code or ZZZZ for unknown
 *    - Examples: B747, A380, C172, ZZZZ
 *
 * 5. WAKE TURBULENCE CATEGORY (Field 9)
 *    - L: Light (MTOW ≤ 7,000 kg)
 *    - M: Medium (7,001 - 136,000 kg)
 *    - H: Heavy (> 136,000 kg)
 *    - J: Super (A380, etc.)
 *    - Rule: IcaoWakeTurbulenceCategory
 *
 * 6. DEPARTURE AERODROME (Field 13)
 *    - Format: 4-character ICAO code or ZZZZ
 *    - Examples: KJFK, LFPG, EGLL, RJTT
 *    - Rule: IcaoAerodrome
 *
 * 7. CRUISING SPEED (Field 15a)
 *    - Format: [Unit][Speed]
 *    - N: Knots (nautical miles)
 *    - M: Mach number (e.g., M0.80)
 *    - K: Kilometers per hour
 *    - Examples: N450, M0.80, K900
 *    - Rule: IcaoCruisingSpeed
 *
 * 8. FLIGHT LEVEL/ALTITUDE (Field 15b)
 *    - Format: FL[level] for flight levels
 *    - Examples: FL250, FL100, F10000 (feet), S5000 (meters)
 *    - Rule: IcaoFlightLevel
 *
 * 9. DESTINATION AERODROME (Field 16)
 *    - Format: 4-character ICAO code or ZZZZ
 *    - Rule: IcaoAerodrome
 *
 * 10. ALTERNATE AERODROMES (Field 17)
 *     - Format: 4-character ICAO codes or ZZZZ
 *     - At least one alternate required for IFR flights
 *     - Rule: IcaoAerodrome
 *
 * ICAO References:
 * ================
 * - ICAO Annex 3: Meteorological Service for International Air Navigation
 * - ICAO Doc 4444: Air Traffic Management Procedures
 * - ICAO Doc 9587: Manual of Air Navigation Services Planning
 *
 * @link https://www.icao.int/Meetings/AMC/MA/2008/Doc%209587%20-%20Manual%20of%20Air%20Navigation%20Services%20Planning.pdf
 */
class IcaoFlightPlanRules
{
    /**
     * Get ICAO field descriptions and rules
     */
    public static function fields(): array
    {
        return [
            'aircraft_identification' => [
                'field' => 7,
                'name' => 'Aircraft Identification',
                'rule' => 'IcaoAircraftIdentification',
                'format' => '1-7 alphanumeric (A-Z, 0-9)',
                'examples' => ['N12345', 'GXABC', 'LFPG1'],
                'description' => 'Designation of aircraft, including registration mark or company/operator code',
            ],
            'flight_rules' => [
                'field' => '8a',
                'name' => 'Flight Rules',
                'rule' => 'IcaoFlightRules',
                'format' => 'Single character: I, V, Y, or Z',
                'examples' => ['I', 'V', 'Y', 'Z'],
                'description' => 'Type of flight operation: I(IFR), V(VFR), Y(IFR→VFR), Z(VFR→IFR)',
            ],
            'type_of_flight' => [
                'field' => '8b',
                'name' => 'Type of Flight',
                'rule' => 'IcaoTypeOfFlight',
                'format' => 'Single character: S, G, M, C, P, T, X',
                'examples' => ['S', 'G', 'M'],
                'description' => 'Nature of the flight',
            ],
            'wake_turbulence_cat' => [
                'field' => '9b',
                'name' => 'Wake Turbulence Category',
                'rule' => 'IcaoWakeTurbulenceCategory',
                'format' => 'L, M, H, or J',
                'examples' => ['L', 'M', 'H'],
                'description' => 'Aircraft wake turbulence category based on MTOW',
            ],
            'departure_aerodrome' => [
                'field' => 13,
                'name' => 'Departure Aerodrome',
                'rule' => 'IcaoAerodrome',
                'format' => '4-character ICAO code or ZZZZ',
                'examples' => ['KJFK', 'LFPG', 'EGLL'],
                'description' => 'ICAO code of aerodrome of departure',
            ],
            'cruising_speed' => [
                'field' => '15a',
                'name' => 'Cruising Speed',
                'rule' => 'IcaoCruisingSpeed',
                'format' => '[Unit][Speed]: N/M/K',
                'examples' => ['N450', 'M0.80', 'K900'],
                'description' => 'Speed in knots (N), Mach (M), or km/h (K)',
            ],
            'level' => [
                'field' => '15b',
                'name' => 'Cruising Level',
                'rule' => 'IcaoFlightLevel',
                'format' => 'FL[altitude]: FL100-FL450',
                'examples' => ['FL250', 'FL100', 'F10000'],
                'description' => 'Cruising level or altitude',
            ],
            'destination_aerodrome' => [
                'field' => 16,
                'name' => 'Destination Aerodrome',
                'rule' => 'IcaoAerodrome',
                'format' => '4-character ICAO code or ZZZZ',
                'examples' => ['KJFK', 'LFPG', 'EGLL'],
                'description' => 'ICAO code of aerodrome of destination',
            ],
        ];
    }

    /**
     * Validate aircraft identification against ICAO rules
     */
    public static function validateAircraftIdentification(string $value): bool
    {
        $rule = new IcaoAircraftIdentification();
        $errors = [];
        $rule->validate('aircraft_identification', $value, function ($message) use (&$errors) {
            $errors[] = $message;
        });
        return empty($errors);
    }

    /**
     * Validate flight rules against ICAO standards
     */
    public static function validateFlightRules(string $value): bool
    {
        $rule = new IcaoFlightRules();
        $errors = [];
        $rule->validate('flight_rules', $value, function ($message) use (&$errors) {
            $errors[] = $message;
        });
        return empty($errors);
    }

    /**
     * Validate type of flight against ICAO standards
     */
    public static function validateTypeOfFlight(string $value): bool
    {
        $rule = new IcaoTypeOfFlight();
        $errors = [];
        $rule->validate('type_of_flight', $value, function ($message) use (&$errors) {
            $errors[] = $message;
        });
        return empty($errors);
    }

    /**
     * Validate wake turbulence category against ICAO standards
     */
    public static function validateWakeTurbulenceCategory(string $value): bool
    {
        $rule = new IcaoWakeTurbulenceCategory();
        $errors = [];
        $rule->validate('wake_turbulence_cat', $value, function ($message) use (&$errors) {
            $errors[] = $message;
        });
        return empty($errors);
    }

    /**
     * Validate aerodrome code against ICAO standards
     */
    public static function validateAerodrome(string $value): bool
    {
        $rule = new IcaoAerodrome();
        $errors = [];
        $rule->validate('aerodrome', $value, function ($message) use (&$errors) {
            $errors[] = $message;
        });
        return empty($errors);
    }

    /**
     * Validate cruising speed against ICAO standards
     */
    public static function validateCruisingSpeed(string $value): bool
    {
        $rule = new IcaoCruisingSpeed();
        $errors = [];
        $rule->validate('cruising_speed', $value, function ($message) use (&$errors) {
            $errors[] = $message;
        });
        return empty($errors);
    }

    /**
     * Validate flight level against ICAO standards
     */
    public static function validateFlightLevel(string $value): bool
    {
        $rule = new IcaoFlightLevel();
        $errors = [];
        $rule->validate('level', $value, function ($message) use (&$errors) {
            $errors[] = $message;
        });
        return empty($errors);
    }
}
