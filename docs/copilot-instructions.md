# ProjectEcho - AI Coding Agent Instructions

## Project Overview

**ProjectEcho** is a Laravel 12 + Filament 5 flight plan management system for ICAO-compliant aircraft flight plan filing, review, and approval. The system validates flight plans against ICAO Annex 3 and Doc 4444 standards and manages their lifecycle from submission through acceptance/rejection.

## Architecture Overview

### Core Domain: Flight Plan Lifecycle
- **Model:** `Flight` (351 fields spanning ICAO Form fields, pilot info, equipment, survival gear)
- **States:** `FlightPlanStatus` enum with 5 states: `Pending` → `Accepted/Rejected` → `Active` → `Completed`
- **Timezone:** Operations use `Asia/Manila`; UTC stored in database; minute-precision for all time fields

### Three-Layer Request Flow
1. **Public submission** → `FlightController::store()` → validates against `StoreFlightPlanRequest` → stores in session
2. **Preview in session** → user reviews → explicit approval creates `Flight` record
3. **ATC reviewer access** via Filament → `showFlightPlanView()` → accept/reject with attestation fields

### ICAO Validation Architecture
Custom validation rules in `app/Rules/` replace generic string validation:
- **8 validation classes** for ICAO-specific fields (aircraft ID, flight rules, speeds, altitudes, aerodrome codes)
- **Helper class** `IcaoFlightPlanRules` with static methods for programmatic validation
- Rules enforce format constraints (e.g., `I`/`V`/`Y`/`Z` for flight rules, `L`/`M`/`H`/`J` for wake turbulence)
- All documented in `ICAO_FLIGHT_PLAN_RULES.md` (full spec), `ICAO_IMPLEMENTATION_SUMMARY.md` (dev guide), `ICAO_QUICK_REFERENCE.md` (cheat sheet)

### Dual Admin Interface
- **Web forms** in `resources/views/flightplan/` for public flight plan entry
- **Filament resources** in `app/Filament/Resources/` with filtered views:
  - `FlightResource` → all flights
  - `ActiveFlights`, `AcceptedFlights`, `RejectedFlights`, `ExpiredFlights`, `LandedFlights` (filtered/custom resources)
  - `Reports` → active flight data aggregation

## Critical Development Patterns

### Time Field Handling
All time fields (proposed_time, total_eet, endurance, etc.) must use `UtcFourDigitTime::normalizeForStorage()`:
```php
// In FlightController::store() and any time input processing
foreach (['proposed_time', 'total_eet', 'endurance'] as $field) {
    if (array_key_exists($field, $validated)) {
        $validated[$field] = UtcFourDigitTime::normalizeForStorage($validated[$field]);
    }
}
```
Minute precision is enforced—seconds are stripped and stored in `TIME` columns.

### String Normalization
Always uppercase string fields (aircraft ID, callsigns, etc.) in controller before persist:
```php
$validated = $this->uppercaseStringFlightFields($validated);
```

### Numeric Field Casting
`persons_on_board`, dinghies columns, and other integers must be normalized:
```php
$validated = $this->normalizeNumericFlightFields($validated);
```

### Request Validation Pattern
Always use `StoreFlightPlanRequest` for flight data—it centralizes ICAO rule enforcement:
```php
// app/Http/Requests/StoreFlightPlanRequest.php contains all ICAO rules()
public function store(StoreFlightPlanRequest $request) {
    $validated = $request->validated(); // Already passed all ICAO checks
}
```

## File Organization Conventions

### By Concern
- **Models:** `app/Models/Flight.php` (351 lines), `app/Models/User.php`
- **Rules:** `app/Rules/` (9 custom validation rules)
- **Services:** `app/Services/FlightPlanICAOFormatter.php` (QR payload generation)
- **Enums:** `app/Enums/FlightPlanStatus.php`
- **Views:** `resources/views/flightplan/` (form, preview, PDF, QR views)
- **Filament:** `app/Filament/Resources/{ResourceName}/` (Pages/, Schemas/)

### Migrations
Name convention: `YYYY_MM_DD_HHMMSS_action.php`
- `0001_01_01_000000_create_users_table.php`
- `2026_04_03_000003_create_flights_table.php` (initial 50 fields)
- `2026_04_20_*` (profile field merges)
- `2026_04_21_*` (status, review, rejection tracking)
- `2026_04_22_000001_normalize_flight_time_precision.php` (retroactive time fix)

## Essential Build & Test Commands

### Development Server
```bash
# XAMPP-based serve (PowerShell or CMD)
.\serve-local.bat                # Runs php artisan serve
# OR manual:
php artisan serve --host=127.0.0.1 --port=8000

# Concurrent watch (npm + artisan)
composer run dev
```

### Testing
```bash
php artisan test                                             # Run all tests (Unit + Feature)
php artisan test tests/Unit/IcaoValidationRulesTest.php     # Unit tests (30+ cases)
php artisan test tests/Feature/FlightPlanIcaoValidationTest.php  # Feature tests (11+ cases)
```

### Asset Building
```bash
npm run build     # Production build (Vite + Tailwind 4)
npm run dev       # Watch mode (Vite)
```

### Database
```bash
php artisan migrate              # Run pending migrations
php artisan migrate:fresh        # Reset + seed
php artisan tinker              # PHP REPL (test validation rules interactively)
```

### Code Quality
```bash
php artisan pint                # Laravel Pint (PSR-12 enforcer)
```

## Integration Points & External Dependencies

### Key Libraries
- **Laravel 12:** Framework core (routing, ORM, migrations, validation)
- **Filament 5:** Admin UI (resources, pages, forms, tables)
- **Vite 6 + Tailwind CSS 4:** Frontend build + styling
- **DomPDF 3.1:** PDF generation (flight plan documents)
- **Simple QRCode 4.2:** QR code generation for ATC scanning
- **BaconQrCode:** Lower-level QR encoding (used in `Encoder` class)

### Cross-Component Communication
- **Session bridge:** User fills form → data stored in session → preview retrieves it → explicit approval creates DB record
- **PDF generation:** `FlightController::storeFlightPlanPdf()` renders `flightplan.pdf` blade view through DomPDF
- **QR encoding:** `generateFlightPlanQrCodeBase64()` uses `FlightPlanICAOFormatter::toICAOMessage()` → payload format: `ECHOFPL|1|DB|{flight_id}`

### Filament Resource Filtering
Each resource resource is accessed via route/middleware; custom Pages/ override defaults:
- `ListFlights.php`, `EditFlight.php`, `CreateFlight.php` in `Flights` resource
- Filtered resources (e.g., `ActiveFlights`) use `getEloquentQuery()` to scope by status

## Common Workflows & Gotchas

### Adding a New ICAO Field
1. Add migration to `database/migrations/` (date-prefixed)
2. Add to `Flight::$fillable` and `Flight::$casts` (if enum/datetime)
3. Create custom rule in `app/Rules/` if non-trivial format
4. Add rule to `StoreFlightPlanRequest::rules()`
5. Update form blade in `resources/views/flightplan/form.blade.php`
6. Document in `ICAO_FLIGHT_PLAN_RULES.md`
7. Add test cases to `tests/Unit/IcaoValidationRulesTest.php` or `tests/Feature/FlightPlanIcaoValidationTest.php`

### Flight Plan Approval Workflow
1. User submits form → `store()` validates, saves to session, redirects to preview
2. User confirms → `approveFlightPlan()` creates `Flight` record with status `Pending`
3. ATC reviewer sees in Filament → views flight via `showFlightPlanView()`
4. If expired (date passed), auto-expires; otherwise reviewer can accept/reject
5. Accept: marks status `Accepted`, records `accepted_by_user_id`, `received_by`, `received_date/time/facility`
6. Reject: marks status `Rejected`, records `rejection_reason`, `rejected_by_wiresign`

### PDF Generation
- Blade template: `resources/views/flightplan/pdf.blade.php`
- Generates with `Barryvdh\DomPDF\Facade\Pdf::loadView()`
- Stored at `storage/app/public/flight-plans/{flight.id}.pdf`
- Cached (checked before regenerating); deleted on status change

### Time Zone Gotcha
- All times in database are stored as TIME (HH:MM, no timezone info)
- Operations timezone defined as `Asia/Manila` constant in `Flight` model
- Incoming times treated as local (Asia/Manila), stored as UTC equivalent
- Be consistent: never mix timezone conversions in controllers

## Code Style & Conventions

- **PHP:** PSR-12 (enforced by `laravel/pint`)
- **Blade:** Two-space indentation, use Filament form classes in admin
- **Eloquent:** Type-hint return types, use `->firstOrFail()` for 404s, avoid lazy N+1 loading
- **Validation:** Always use request form classes (e.g., `StoreFlightPlanRequest`), never raw `validate()` in controller
- **Database:** Use migrations for schema changes; avoid raw SQL

## Testing Strategy

- **Unit tests** in `tests/Unit/`: Test isolated classes (validation rules, helpers)
- **Feature tests** in `tests/Feature/`: Test HTTP flows (form submission, approval, rejection)
- **PHPUnit config** in `phpunit.xml`: Uses SQLite in-memory DB, synchronous queue, array mail driver
- **Test database:** See `phpunit.xml` for env overrides (`DB_DATABASE=:memory:`)

## Relevant Documentation Files

- **ICAO_FLIGHT_PLAN_RULES.md** → Complete ICAO field reference, error messages, integration examples
- **ICAO_IMPLEMENTATION_SUMMARY.md** → Dev guide: what was built, file structure, testing commands
- **ICAO_QUICK_REFERENCE.md** → Cheat sheet: quick field formats, common customizations, examples
- **README.md** → Standard Laravel boilerplate (not project-specific)
- **compose.json** / **package.json** → Dependency lists and build scripts

## Repository Structure at a Glance

```
ProjectEcho/
├── app/
│   ├── Models/Flight.php              # 351-field domain model
│   ├── Http/Controllers/FlightController.php
│   ├── Http/Requests/StoreFlightPlanRequest.php
│   ├── Rules/                         # 8 ICAO validation rules
│   ├── Enums/FlightPlanStatus.php
│   ├── Services/FlightPlanICAOFormatter.php
│   └── Filament/Resources/            # Admin UI (Flights, Active, Rejected, etc.)
├── database/
│   ├── migrations/                    # 12+ migration files (2026 dated)
│   └── factories/UserFactory.php
├── resources/
│   ├── views/flightplan/              # Public-facing forms, preview, PDF, QR
│   └── css/js/                        # Tailwind + Vite assets
├── routes/web.php                     # 13 flight-related routes
├── tests/
│   ├── Unit/IcaoValidationRulesTest.php
│   └── Feature/FlightPlanIcaoValidationTest.php
├── vite.config.js                     # Vite + Tailwind 4 + Laravel plugin
├── package.json                       # Node deps + build scripts
├── composer.json                      # PHP 8.2, Laravel 12, Filament 5
├── phpunit.xml                        # Test suite config
├── serve-local.bat                    # XAMPP dev server launcher (Windows)
├── ICAO_FLIGHT_PLAN_RULES.md          # Full field documentation
├── ICAO_IMPLEMENTATION_SUMMARY.md     # Dev guide
└── ICAO_QUICK_REFERENCE.md            # Cheat sheet
```

---

**Last Updated:** May 2, 2026 | **PHP:** 8.2+ | **Laravel:** 12 | **Filament:** 5 | **Node:** 18+
