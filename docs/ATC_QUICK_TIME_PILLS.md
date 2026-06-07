# ATC Quick-Time Pills

Operational flight tables use quick-time pill columns such as `NOW`, `AIRBORNE`, `LANDED`, and `ENGINE OFF` beside their editable time inputs.

These pills are safety-critical workflow shortcuts for ATC users. They let a controller record the current UTC time with one confirmed click instead of manually typing a four-digit time while managing live traffic.

## Why They Matter

- They reduce workload during time-sensitive control tasks.
- They avoid typing errors in operational time fields.
- They preserve the confirmation modal flow before writing the current UTC time.
- They keep the typed input available for corrections or delayed reporting.

## Current Mapping

- Ready Flights: `NOW` updates `time_start_up`.
- Active Flights: `AIRBORNE` updates `time_airborne`.
- Airborne Flights: `LANDED` updates `time_touchdown`.
- Landed Flights: `ENGINE OFF` updates `time_shutdown`.

## Implementation Notes

The pills are `TextColumn` instances with the `echo-status-time-now-trigger` class and data attributes that call Livewire methods such as `confirmStartUpNow`, `confirmAirborneNow`, `confirmTouchdownNow`, and `confirmShutdownNow`.

The browser-side confirmation behavior lives in `resources/views/filament/components/echo-modal-root.blade.php`.

Do not remove these pill columns when simplifying table layouts unless the replacement still gives ATC users a one-click, confirmed way to set the current UTC operational time.
