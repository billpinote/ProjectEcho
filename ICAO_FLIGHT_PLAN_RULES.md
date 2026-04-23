# ICAO Flight Plan Filing Rules Implementation

This document provides a comprehensive guide to the ICAO flight plan validation rules implemented in ProjectEcho.

## Overview

The application enforces ICAO Annex 3 and ICAO Doc 4444 standards for flight plan filing. All validation rules are located in `app/Rules/` directory.

## ICAO Flight Plan Fields and Rules

### 1. Aircraft Identification (Field 7)

**Standard:** ICAO Annex 3 / Doc 4444

**Format:** 2-7 alphanumeric characters (A-Z, 0-9)

**Validation Class:** `IcaoAircraftIdentification`

**Examples:**
- `N12345` - US civil aircraft
- `GXABC` - UK civil aircraft  
- `5YTJK` - Tanzania civil aircraft
- `LFPG1` - French aircraft

**Implementation in StoreFlightPlanRequest:**
```php
'aircraft_identification' => ['nullable', new IcaoAircraftIdentification()],
```

**Usage in Controller:**
```php
$validated = $request->validated(); // Field is automatically validated
$flight = Flight::create($validated);
```

---

### 2. Flight Rules (Field 8a)

**Standard:** ICAO Annex 3

**Allowed Values (Single Character):**
- `I` - Instrument Flight Rules
- `V` - Visual Flight Rules
- `Y` - Special Visual Flight Rules
- `Z` - Formation Flight Rules

**Validation Class:** `IcaoFlightRules`

**Examples:**
- `I` - Instrument Flight Rules
- `V` - Visual Flight Rules
- `Y` - Special Visual Flight Rules
- `Z` - Formation Flight Rules

**Implementation in StoreFlightPlanRequest:**
```php
'flight_rules' => ['nullable', new IcaoFlightRules()],
```

---

### 3. Type of Flight (Field 8b)

**Standard:** ICAO Annex 3

**Format:** Single character

**Allowed Values:**
| Code | Description |
|------|-------------|
| S | Scheduled air service |
| N | Non-scheduled air transport (charter) |
| G | General aviation |
| M | Military |
| X | Other |

**Validation Class:** `IcaoTypeOfFlight`

**Implementation in StoreFlightPlanRequest:**
```php
'type_of_flight' => ['nullable', new IcaoTypeOfFlight()],
```

**Get Descriptions:**
```php
use App\Rules\IcaoTypeOfFlight;

$descriptions = IcaoTypeOfFlight::descriptions();
// Returns array with all type descriptions
```

---

### 4. Wake Turbulence Category (Field 9b)

**Standard:** ICAO Annex 3

**Validation Class:** `IcaoWakeTurbulenceCategory`

**Categories Based on MTOW:**

| Category | Description | MTOW |
|----------|-------------|------|
| L | Light | ≤ 7,000 kg |
| M | Medium | 7,001 - 136,000 kg |
| H | Heavy | > 136,000 kg |
| J | Super | A380, Airbus A400M, etc. |

**Examples:**
- Light Aircraft: Cessna 172, Piper PA-28
- Medium Aircraft: Boeing 737, Airbus A320
- Heavy Aircraft: Boeing 747, Airbus A380
- Super: A380

**Implementation in StoreFlightPlanRequest:**
```php
'wake_turbulence_cat' => ['nullable', new IcaoWakeTurbulenceCategory()],
```

---

### 5. Departure Aerodrome (Field 13)

**Standard:** ICAO Annex 3

**Format:** 4-character ICAO code or ZZZZ

**Validation Class:** `IcaoAerodrome`

**Examples:**
- `KJFK` - John F. Kennedy International (New York, USA)
- `LFPG` - Charles de Gaulle (Paris, France)
- `EGLL` - Heathrow (London, UK)
- `RJTT` - Haneda (Tokyo, Japan)
- `SBGR` - Galeão (Rio de Janeiro, Brazil)
- `ZZZZ` - Aerodrome not in ICAO registry

**Important:** When using `ZZZZ`, you must include `DEP/[aerodrome details]` in the Other Information field.

**Implementation in StoreFlightPlanRequest:**
```php
'departure_aerodrome' => ['nullable', new IcaoAerodrome()],
```

---

### 6. Cruising Speed (Field 15a)

**Standard:** ICAO Doc 4444

**Format:** `[Unit][Speed]`

**Units:**
- `N` - Knots (nautical miles per hour)
- `M` - Mach number (e.g., M0.75 for subsonic speed)
- `K` - Kilometers per hour

**Examples:**
- `N450` - 450 knots
- `N0500` - 500 knots (leading zeros allowed)
- `M0.80` - Mach 0.80
- `M0.85` - Mach 0.85
- `K900` - 900 kilometers per hour

**Validation Class:** `IcaoCruisingSpeed`

**Validation Rules:**
- Mach numbers must be between 0.1 and 2.0
- 3-4 digits for speed value
- Optional decimal for Mach numbers

**Implementation in StoreFlightPlanRequest:**
```php
'cruising_speed' => ['nullable', new IcaoCruisingSpeed()],
```

---

### 7. Cruising Level/Altitude (Field 11)

**Standard:** ICAO Annex 3 / Doc 4444

**Format Options:**

1. **Flight Level:** `F[3 digits]`
   - Indicates flight level in hundreds of feet
   - Examples: `F100` (10,000 ft), `F330` (33,000 ft)
   - Range: F100 to F450

2. **Altitude in Feet:** `A[3-4 digits]`
   - Specific altitude in hundreds of feet
   - Examples: `A045` (4,500 ft), `A0100` (10,000 ft)
   - Range: A001 to A9999

3. **Altitude in Meters:** `S[4 digits]`
   - Used in regions employing metric altitudes
   - Examples: `S1130` (11,300 meters)

4. **Visual Flight Rules:** `VFR`
   - Used for VFR flights without specific altitude assignment

**Validation Class:** `IcaoFlightLevel`

**Valid Examples:**
- Flight Levels: F100, F150, F250, F350, F450
- Altitudes: A045, A100, A5000
- Metric: S1130, S5000
- VFR: VFR

**Invalid Examples:**
- FL100 (incorrect prefix)
- 250 (missing prefix)
- F50 (insufficient digits)
- F999 (exceeds maximum)

**Implementation in StoreFlightPlanRequest:**
```php
'level' => ['nullable', new IcaoFlightLevel()],
```

---

### 8. Destination Aerodrome (Field 16)

**Standard:** ICAO Annex 3

**Format:** 4-character ICAO code or ZZZZ

**Validation Class:** `IcaoAerodrome`

**Same rules as Departure Aerodrome**

**Implementation in StoreFlightPlanRequest:**
```php
'destination_aerodrome' => ['nullable', new IcaoAerodrome()],
```

---

### 9. Alternate Aerodromes (Field 17)

**Standard:** ICAO Annex 3

**Requirements:**
- I (IFR) flights: At least two alternate aerodromes required (one required, one recommended)
- V (VFR) flights: One alternate recommended
- Format: 4-character ICAO codes or ZZZZ

**Validation Class:** `IcaoAerodrome`

**Implementation in StoreFlightPlanRequest:**
```php
'altn_aerodrome_1' => ['nullable', new IcaoAerodrome()],
'altn_aerodrome_2' => ['nullable', new IcaoAerodrome()],
```

---

## Using ICAO Validation Rules

### In Forms

The form fields will automatically validate against ICAO standards:

```blade
{{-- Flight Plan Form Example --}}
<form action="{{ route('flightplan.store') }}" method="POST">
    @csrf

    <input type="text" name="aircraft_identification" 
           value="{{ old('aircraft_identification') }}"
           placeholder="Aircraft ID (e.g., N12345)">
    @error('aircraft_identification')
        <span class="error">{{ $message }}</span>
    @enderror

    <select name="flight_rules">
        <option value="">Select...</option>
        <option value="I">I - Instrument Flight Rules</option>
        <option value="V">V - Visual Flight Rules</option>
        <option value="Y">Y - Special Visual Flight Rules</option>
        <option value="Z">Z - Formation Flight Rules</option>
    </select>
    @error('flight_rules')
        <span class="error">{{ $message }}</span>
    @enderror

    <button type="submit">File Flight Plan</button>
</form>
```

### In Controllers

```php
namespace App\Http\Controllers;

use App\Http\Requests\StoreFlightPlanRequest;
use App\Models\Flight;

class FlightController extends Controller
{
    public function store(StoreFlightPlanRequest $request)
    {
        // All data is automatically validated against ICAO rules
        $validated = $request->validated();
        
        $flight = Flight::create($validated);
        
        return redirect()
            ->route('flightplan')
            ->with('status', 'Flight plan filed successfully!');
    }
}
```

### Direct Validation Usage

```php
use App\Rules\IcaoFlightPlanRules;

// Validate individual fields programmatically
if (IcaoFlightPlanRules::validateAircraftIdentification('N12345')) {
    // Valid
}

if (IcaoFlightPlanRules::validateFlightRules('I')) {
    // Valid
}

// Get field information
$fieldInfo = IcaoFlightPlanRules::fields();
foreach ($fieldInfo as $field => $info) {
    echo $info['name'] . ': ' . $info['format'];
}
```

---

## Validation Error Messages

When validation fails, users see ICAO-compliant error messages:

**Aircraft Identification:**
```
The aircraft identification must contain 2-7 alphanumeric characters 
(A-Z, 0-9) according to ICAO standards.
```

**Flight Rules:**
```
The flight rules must be one of: I (IFR), V (VFR), Y (IFR then VFR), Z (VFR then IFR) 
according to ICAO standards.
```

**Cruising Speed:**
```
The cruising speed must be in ICAO format: N[speed in knots], 
M[Mach number], or K[speed in km/h]. Examples: N450, M0.80, K900.
```

---

## Testing ICAO Validation

### Valid Examples

```php
// Valid Aircraft Identifications
'N12345', 'GXABC', '5YTJK', 'LFPG1'

// Valid Flight Rules
'I', 'V', 'Y', 'Z'

// Valid Types of Flight
'S', 'G', 'M', 'C', 'P', 'T', 'X'

// Valid Wake Turbulence Categories
'L', 'M', 'H', 'J'

// Valid Aerodromes
'KJFK', 'LFPG', 'EGLL', 'RJTT', 'ZZZZ'

// Valid Cruising Speeds
'N450', 'M0.80', 'K900', 'N0500'

// Valid Flight Levels
'FL250', 'FL100', 'F10000', 'S5000'
```

### Invalid Examples

```php
// Invalid Aircraft Identification (too long, contains hyphen)
'N-12345'

// Invalid Flight Rules
'IFR', 'VISUAL', 'VFR', 'X'

// Invalid Type of Flight (not single character)
'SC', 'SCHEDULED'

// Invalid Aerodrome (not 4 characters)
'JFK', 'KJFK1'

// Invalid Cruising Speed (wrong format)
'450', 'MACH0.80'

// Invalid Flight Level
'FL250A', '250', 'FL000'
```

---

## ICAO Standards References

- **ICAO Annex 3:** Meteorological Service for International Air Navigation
- **ICAO Doc 4444:** Air Traffic Management Procedures (PANS-ATM)
- **ICAO Doc 9587:** Manual of Air Navigation Services Planning (PANS-NAV)

---

## Integration with Other Features

The ICAO validation rules integrate seamlessly with:

1. **Form Validation:** Automatic client & server-side validation
2. **Error Handling:** User-friendly ICAO-compliant error messages
3. **Database Storage:** Validated data stored in flights table
4. **PDF Generation:** ICAO-formatted data included in flight plan PDFs
5. **Other Information Field:** Works with existing ZZZZ special handling

---

## Extending the Rules

To add custom validation rules, create a new class in `app/Rules/`:

```php
namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class CustomIcaoRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Your validation logic here
    }
}
```

Then use it in `StoreFlightPlanRequest`:

```php
'field_name' => ['nullable', new CustomIcaoRule()],
```

---

**Last Updated:** April 2026  
**ICAO Standards Version:** Latest (as of implementation date)
