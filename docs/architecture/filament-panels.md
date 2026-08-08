# Filament Panels

Project Echo uses Filament panels to separate user workflows.

## Panel-Owned Code

Panel-owned code belongs under `app/Filament/Panels`.

Examples:

- Pilot profile, preferences, security, and dashboard UI belongs in `app/Filament/Panels/Pilot`.
- ATMO-only pages such as Alpha and Coordinator belong in `app/Filament/Panels/Atmo`.

## Shared Code

Shared Filament code belongs under `app/Filament/Shared`.

Examples:

- Flight operation resources used by ATMO, ATS, Dispatch, Avsec, or Artisan.
- Shared flight forms and tables.
- Shared utility pages such as QR scan import.

## Rule Of Thumb

Do not put a class under `Panels/Atmo` just because ATMO uses it. If ATS, Dispatch, Avsec, Admin, Artisan, or Pilot also register that class, it belongs under `Shared`.

This keeps the folder structure honest and prevents future programmers from guessing which panel owns shared workflows.

