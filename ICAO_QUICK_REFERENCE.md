# ICAO Flight Plan Rules - Quick Reference Card

## 🎯 Summary of Implementation

Your application now enforces ICAO flight plan filing rules with 7 custom validation rules.

---

## 📋 ICAO Field Rules Cheat Sheet

### Aircraft Identification (Field 7)
```
Format: 2-7 alphanumeric characters (A-Z, 0-9)
✓ N12345, GXABC, 5YTJK, LFPG1, C747, HB
✗ N, N-12345, TOOLONG123, N 12345
Rule: new IcaoAircraftIdentification()
Minimum: 2 characters, Maximum: 7 characters
```

### Flight Rules (Field 8a)
```
Format: Single character
I = IFR (Instrument Flight Rules) - entire flight under IFR
V = VFR (Visual Flight Rules) - entire flight under VFR
Y = IFR first, then VFR (mixed flight rules, IFR to VFR transition)
Z = VFR first, then IFR (mixed flight rules, VFR to IFR transition)
✓ I, V, Y, Z
✗ IFR, VFR, SVFR, FFR, INVALID
Rule: new IcaoFlightRules()
```

### Type of Flight (Field 8b)
```
Format: Single character
Options: S(Scheduled), N(Non-scheduled/Charter), G(General), 
         M(Military), X(Other)
✓ S, N, G, M, X
✗ C, P, T, SC, SCHEDULED, 1, Z
Rule: new IcaoTypeOfFlight()
```

### Wake Turbulence Category (Field 9b)
```
Format: Single character
L = Light (≤7,000 kg)
M = Medium (7,001-136,000 kg)
H = Heavy (>136,000 kg)
J = Super (A380, A400M)
✓ L, M, H, J
✗ HEAVY, LM, LIGHT
Rule: new IcaoWakeTurbulenceCategory()
```

### Aerodromes (Fields 13, 16, 17)
```
Format: 4-letter ICAO code OR ZZZZ
✓ KJFK, LFPG, EGLL, RJTT, ZZZZ
✗ JFK, KJFK1, kjfk
Rule: new IcaoAerodrome()
Note: ZZZZ requires DEP/DEST/ALTN tags in Other Information
```

### Cruising Speed (Field 15a)
```
Format: [Unit][Speed]
N = Knots | M = Mach | K = Kilometers/hour
✓ N450, M0.80, K900, N0500
✗ 450, MACH0.80, N 450
Rule: new IcaoCruisingSpeed()
Note: Mach must be 0.1 to 2.0
```

### Flight Level/Altitude (Field 11)
```
Format: F/S/A prefix + digits or VFR
F100 = Flight Level 100 (10,000 ft)
F330 = Flight Level 330 (33,000 ft)
A045 = Altitude 4,500 ft (in hundreds of feet)
S1130 = 11,300 meters (metric)
VFR = Visual Flight Rules
✓ F100, F330, A045, S1130, VFR
✗ FL100, 100, 10000, 333
Rule: new IcaoFlightLevel()
Range: F100-F450, A001-A9999
```

---

## 🚀 Quick Start Using ICAO Rules

### In Views (Blade)
```blade
<input type="text" name="aircraft_identification" 
       placeholder="N12345" value="{{ old('aircraft_identification') }}">
@error('aircraft_identification')
    <span class="text-red-500">{{ $message }}</span>
@enderror
```

### In Controllers
```php
public function store(StoreFlightPlanRequest $request)
{
    // Automatically validated against ICAO rules
    $validated = $request->validated();
    $flight = Flight::create($validated);
}
```

### Programmatic Validation
```php
use App\Rules\IcaoFlightPlanRules;

if (IcaoFlightPlanRules::validateAircraftIdentification('N12345')) {
    // Valid!
}
```

---

## 📁 Files Created/Modified

### New Files (8 Rules + 2 Tests + 2 Docs)
```
app/Rules/
├── IcaoAircraftIdentification.php
├── IcaoFlightRules.php
├── IcaoTypeOfFlight.php
├── IcaoWakeTurbulenceCategory.php
├── IcaoAerodrome.php
├── IcaoCruisingSpeed.php
├── IcaoFlightLevel.php
└── IcaoFlightPlanRules.php

tests/Unit/
└── IcaoValidationRulesTest.php (30+ cases)

tests/Feature/
└── FlightPlanIcaoValidationTest.php (11+ cases)

Root/
├── ICAO_FLIGHT_PLAN_RULES.md (Full documentation)
├── ICAO_IMPLEMENTATION_SUMMARY.md (Developer guide)
└── ICAO_QUICK_REFERENCE.md (This file)
```

### Modified Files (1)
```
app/Http/Requests/
└── StoreFlightPlanRequest.php (Added ICAO rule imports)
```

---

## ✅ Testing

### Run Unit Tests
```bash
php artisan test tests/Unit/IcaoValidationRulesTest.php
```

### Run Feature Tests
```bash
php artisan test tests/Feature/FlightPlanIcaoValidationTest.php
```

### Test Specific Rule
```php
// In tinker or test
$rule = new \App\Rules\IcaoAircraftIdentification();
$errors = [];
$rule->validate('aircraft_id', 'N12345', fn() => $errors[] = true);
var_dump($errors); // Empty = valid
```

---

## 🛠️ Common Customizations

### Add Your Own Rule
```php
// app/Rules/MyIcaoRule.php
namespace App\Rules;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class MyIcaoRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (/* invalid */) {
            $fail('Custom error message');
        }
    }
}
```

Then use in request:
```php
'field_name' => ['nullable', new MyIcaoRule()],
```

---

## 📚 ICAO Standards Referenced

- **ICAO Annex 3:** Meteorological Service
- **ICAO Doc 4444:** Air Traffic Management
- **ICAO Doc 9587:** NAV Services Planning

---

## 🎓 Example Flight Plans

### Valid Instrument Flight (I)
```
Aircraft:     N12345
Rules:        I (Instrument Flight Rules)
Type:         S (Scheduled)
WTC:          H (Heavy)
Departure:    KJFK
Speed:        N450
Level:        FL350
Route:        KJFK DIRECT LFPG
Destination:  LFPG
Alternate 1:  EGLL
Alternate 2:  LEMD
```

### Valid Visual Flight (V)
```
Aircraft:     GXABC
Rules:        V (Visual Flight Rules)
Type:         G (General Aviation)
WTC:          L (Light)
Departure:    EGLL
Speed:        N120
Level:        F5000
Route:        EGLL RUNWAY 27L
Destination:  KORD
```

---

## ⚠️ Common Mistakes

| ❌ Wrong | ✅ Correct | Issue |
|---------|----------|-------|
| N-12345 | N12345 | No hyphens in ICAO |
| IFR | I | Correct format is single letter |
| VFR | V | Correct format is single letter |
| SC | S or C | Must be single char |
| JFK | KJFK | Must be 4 characters |
| 450 | N450 | Missing unit (N/M/K) |
| 350 | FL350 | Missing FL prefix |

---

## 🔗 Related Documentation

- **Full Guide:** `ICAO_FLIGHT_PLAN_RULES.md`
- **Integration Guide:** `ICAO_IMPLEMENTATION_SUMMARY.md`
- **This Guide:** `ICAO_QUICK_REFERENCE.md`

---

## 💡 Tips

1. **Optional Fields:** All ICAO-validated fields are nullable - only validate if provided
2. **Case Insensitive:** All validations convert to uppercase automatically
3. **ZZZZ Special:** When using ZZZZ for unknown aerodromes, include descriptive info in Other Information field
4. **Error Messages:** All error messages are ICAO-compliant and user-friendly
5. **Database Safe:** Validation is non-breaking - all existing data remains compatible

---

## 🎉 You're All Set!

Your ICAO flight plan validation system is ready to use. Files are in place, tests are written, and documentation is complete!

Happy flight planning! ✈️
