<?php

namespace Tests\Unit;

use App\Rules\IcaoAerodrome;
use App\Rules\IcaoAircraftIdentification;
use App\Rules\IcaoCruisingSpeed;
use App\Rules\IcaoFlightLevel;
use App\Rules\IcaoFlightPlanRules;
use App\Rules\IcaoFlightRules;
use App\Rules\IcaoTypeOfFlight;
use App\Rules\IcaoWakeTurbulenceCategory;
use App\Rules\UtcFourDigitTime;
use PHPUnit\Framework\TestCase;

class IcaoValidationRulesTest extends TestCase
{
    /**
     * Test ICAO Aircraft Identification validation
     */
    public function test_aircraft_identification_valid(): void
    {
        $rule = new IcaoAircraftIdentification;
        $errors = [];

        $validIdentifications = ['N12345', 'GXABC', '5YTJK', 'LFPG1', 'KORD', 'A380'];

        foreach ($validIdentifications as $identification) {
            $rule->validate('aircraft_identification', $identification, function () use (&$errors) {
                $errors[] = true;
            });
            $this->assertEmpty($errors, "Identification '$identification' should be valid");
        }
    }

    /**
     * Test ICAO Aircraft Identification validation - Invalid
     */
    public function test_aircraft_identification_invalid(): void
    {
        $rule = new IcaoAircraftIdentification;
        $errors = [];

        // Invalid: too short (< 2), too long (> 7), special characters, spaces
        $invalidIdentifications = ['N', 'N-12345', 'TOOLONG123', 'N 12345', 'N12345/A'];

        foreach ($invalidIdentifications as $identification) {
            $errors = [];
            $rule->validate('aircraft_identification', $identification, function () use (&$errors) {
                $errors[] = true;
            });
            $this->assertNotEmpty($errors, "Identification '$identification' should be invalid");
        }
    }

    /**
     * Test ICAO Flight Rules validation
     */
    public function test_flight_rules_valid(): void
    {
        $rule = new IcaoFlightRules;
        $errors = [];

        $validRules = ['I', 'V', 'Y', 'Z'];

        foreach ($validRules as $flightRule) {
            $errors = [];
            $rule->validate('flight_rules', $flightRule, function () use (&$errors) {
                $errors[] = true;
            });
            $this->assertEmpty($errors, "Flight rule '$flightRule' should be valid");
        }
    }

    /**
     * Test ICAO Flight Rules validation - Invalid
     */
    public function test_flight_rules_invalid(): void
    {
        $rule = new IcaoFlightRules;
        $errors = [];

        $invalidRules = ['IFR', 'VFR', 'SVFR', 'FFR', 'X'];

        foreach ($invalidRules as $flightRule) {
            $errors = [];
            $rule->validate('flight_rules', $flightRule, function () use (&$errors) {
                $errors[] = true;
            });
            $this->assertNotEmpty($errors, "Flight rule '$flightRule' should be invalid");
        }
    }

    /**
     * Test ICAO Type of Flight validation
     */
    public function test_type_of_flight_valid(): void
    {
        $rule = new IcaoTypeOfFlight;
        $errors = [];

        // Updated to ICAO standard: S(Scheduled), N(Non-scheduled), G(General), M(Military), X(Other)
        $validTypes = ['S', 'N', 'G', 'M', 'X'];

        foreach ($validTypes as $type) {
            $errors = [];
            $rule->validate('type_of_flight', $type, function () use (&$errors) {
                $errors[] = true;
            });
            $this->assertEmpty($errors, "Type '$type' should be valid");
        }
    }

    /**
     * Test ICAO Type of Flight validation - Invalid
     */
    public function test_type_of_flight_invalid(): void
    {
        $rule = new IcaoTypeOfFlight;
        $errors = [];

        // Old codes (C, P, T) and other invalid codes should fail
        $invalidTypes = ['SC', 'SCHEDULED', 'Z', '1', 'C', 'P', 'T'];

        foreach ($invalidTypes as $type) {
            $errors = [];
            $rule->validate('type_of_flight', $type, function () use (&$errors) {
                $errors[] = true;
            });
            $this->assertNotEmpty($errors, "Type '$type' should be invalid");
        }
    }

    /**
     * Test ICAO Wake Turbulence Category validation
     */
    public function test_wake_turbulence_category_valid(): void
    {
        $rule = new IcaoWakeTurbulenceCategory;
        $errors = [];

        $validCategories = ['L', 'M', 'H', 'J'];

        foreach ($validCategories as $category) {
            $errors = [];
            $rule->validate('wake_turbulence_cat', $category, function () use (&$errors) {
                $errors[] = true;
            });
            $this->assertEmpty($errors, "Category '$category' should be valid");
        }
    }

    /**
     * Test ICAO Wake Turbulence Category validation - Invalid
     */
    public function test_wake_turbulence_category_invalid(): void
    {
        $rule = new IcaoWakeTurbulenceCategory;
        $errors = [];

        $invalidCategories = ['LM', 'LIGHT', 'X'];

        foreach ($invalidCategories as $category) {
            $errors = [];
            $rule->validate('wake_turbulence_cat', $category, function () use (&$errors) {
                $errors[] = true;
            });
            $this->assertNotEmpty($errors, "Category '$category' should be invalid");
        }
    }

    /**
     * Test ICAO Aerodrome validation
     */
    public function test_aerodrome_valid(): void
    {
        $rule = new IcaoAerodrome;
        $errors = [];

        $validAerodromes = ['KJFK', 'LFPG', 'EGLL', 'RJTT', 'SBGR', 'ZZZZ'];

        foreach ($validAerodromes as $aerodrome) {
            $errors = [];
            $rule->validate('departure_aerodrome', $aerodrome, function () use (&$errors) {
                $errors[] = true;
            });
            $this->assertEmpty($errors, "Aerodrome '$aerodrome' should be valid");
        }
    }

    /**
     * Test ICAO Aerodrome validation - Invalid
     */
    public function test_aerodrome_invalid(): void
    {
        $rule = new IcaoAerodrome;
        $errors = [];

        $invalidAerodromes = ['JFK', 'KJFK1', 'K JFK', 'ZZZZA'];

        foreach ($invalidAerodromes as $aerodrome) {
            $errors = [];
            $rule->validate('departure_aerodrome', $aerodrome, function () use (&$errors) {
                $errors[] = true;
            });
            $this->assertNotEmpty($errors, "Aerodrome '$aerodrome' should be invalid");
        }
    }

    /**
     * Test ICAO Cruising Speed validation
     */
    public function test_cruising_speed_valid(): void
    {
        $rule = new IcaoCruisingSpeed;
        $errors = [];

        $validSpeeds = ['N450', 'N0500', 'M0.80', 'M0.85', 'K900', 'N0200'];

        foreach ($validSpeeds as $speed) {
            $errors = [];
            $rule->validate('cruising_speed', $speed, function () use (&$errors) {
                $errors[] = true;
            });
            $this->assertEmpty($errors, "Speed '$speed' should be valid");
        }
    }

    /**
     * Test ICAO Cruising Speed validation - Invalid
     */
    public function test_cruising_speed_invalid(): void
    {
        $rule = new IcaoCruisingSpeed;
        $errors = [];

        $invalidSpeeds = ['450', 'MACH0.80', 'N 450', 'M10.0', 'K90000'];

        foreach ($invalidSpeeds as $speed) {
            $errors = [];
            $rule->validate('cruising_speed', $speed, function () use (&$errors) {
                $errors[] = true;
            });
            $this->assertNotEmpty($errors, "Speed '$speed' should be invalid");
        }
    }

    /**
     * Test ICAO Flight Level validation
     */
    public function test_flight_level_valid(): void
    {
        $rule = new IcaoFlightLevel;
        $errors = [];

        // F + 3 digits for flight level, A + 3-4 digits for altitude, S + 4 digits for metric, VFR
        $validLevels = ['F100', 'F250', 'F350', 'F450', 'A045', 'A0100', 'A5000', 'S1130', 'S5000', 'VFR'];

        foreach ($validLevels as $level) {
            $errors = [];
            $rule->validate('level', $level, function () use (&$errors) {
                $errors[] = true;
            });
            $this->assertEmpty($errors, "Level '$level' should be valid");
        }
    }

    /**
     * Test ICAO Flight Level validation - Invalid
     */
    public function test_flight_level_invalid(): void
    {
        $rule = new IcaoFlightLevel;
        $errors = [];

        // Invalid: wrong prefix, wrong digit count, out of range, etc.
        $invalidLevels = ['FL250', '250', 'F99', 'F451', 'A00', 'A99999', 'S123', 'S99999', 'VF', 'VFRS'];

        foreach ($invalidLevels as $level) {
            $errors = [];
            $rule->validate('level', $level, function () use (&$errors) {
                $errors[] = true;
            });
            $this->assertNotEmpty($errors, "Level '$level' should be invalid");
        }
    }

    /**
     * Test UTC 4-digit time validation
     */
    public function test_utc_four_digit_time_valid(): void
    {
        $rule = new UtcFourDigitTime;
        $errors = [];

        $validTimes = ['0000', '0015', '1200', '2359'];

        foreach ($validTimes as $time) {
            $errors = [];
            $rule->validate('proposed_time', $time, function () use (&$errors) {
                $errors[] = true;
            });
            $this->assertEmpty($errors, "Time '$time' should be valid");
        }
    }

    /**
     * Test UTC 4-digit time validation - Invalid
     */
    public function test_utc_four_digit_time_invalid(): void
    {
        $rule = new UtcFourDigitTime;
        $errors = [];

        $invalidTimes = ['2400', '2360', '12:00', '123', '12345', 'abcd'];

        foreach ($invalidTimes as $time) {
            $errors = [];
            $rule->validate('proposed_time', $time, function () use (&$errors) {
                $errors[] = true;
            });
            $this->assertNotEmpty($errors, "Time '$time' should be invalid");
        }
    }

    /**
     * Test ICAO Flight Plan Rules helper methods
     */
    public function test_icao_flight_plan_rules_helper(): void
    {
        $this->assertTrue(IcaoFlightPlanRules::validateAircraftIdentification('N12345'));
        $this->assertFalse(IcaoFlightPlanRules::validateAircraftIdentification('N-12345'));

        $this->assertTrue(IcaoFlightPlanRules::validateFlightRules('I'));
        $this->assertFalse(IcaoFlightPlanRules::validateFlightRules('IFR'));

        $this->assertTrue(IcaoFlightPlanRules::validateTypeOfFlight('S'));
        $this->assertFalse(IcaoFlightPlanRules::validateTypeOfFlight('SC'));

        $this->assertTrue(IcaoFlightPlanRules::validateWakeTurbulenceCategory('H'));
        $this->assertFalse(IcaoFlightPlanRules::validateWakeTurbulenceCategory('X'));

        $this->assertTrue(IcaoFlightPlanRules::validateAerodrome('KJFK'));
        $this->assertFalse(IcaoFlightPlanRules::validateAerodrome('JFK'));

        $this->assertTrue(IcaoFlightPlanRules::validateCruisingSpeed('N450'));
        $this->assertFalse(IcaoFlightPlanRules::validateCruisingSpeed('450'));

        $this->assertTrue(IcaoFlightPlanRules::validateFlightLevel('F250'));
        $this->assertTrue(IcaoFlightPlanRules::validateFlightLevel('VFR'));
        $this->assertFalse(IcaoFlightPlanRules::validateFlightLevel('FL250'));
    }
}
