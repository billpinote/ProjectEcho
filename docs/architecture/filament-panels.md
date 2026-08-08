# Filament Panels

Project Echo uses Filament panels to separate user workflows.

## Panel-Owned Code

Panel-owned code belongs under `app/Filament/Panels/{PanelName}`. Every active panel provider should have a matching panel folder:

- `app/Filament/Panels/Admin`
- `app/Filament/Panels/Artisan`
- `app/Filament/Panels/Atmo`
- `app/Filament/Panels/Ats`
- `app/Filament/Panels/Avsec`
- `app/Filament/Panels/Dispatch`
- `app/Filament/Panels/Pilot`

Examples:

- Pilot profile, preferences, security, dashboard UI, and pilot-only resources belong in `app/Filament/Panels/Pilot`.
- ATMO pages such as Alpha and Coordinator belong in `app/Filament/Panels/Atmo`.
- Admin profile-management resources registered by `AdminPanelProvider` belong in `app/Filament/Panels/Admin`.
- Dispatch operational resources registered by `DispatchPanelProvider` belong in `app/Filament/Panels/Dispatch`.

## Shared Code

Shared Filament code belongs under `app/Filament/Shared`.

Examples:

- Shared flight forms, tables, schemas, mutation behavior, and common query logic.
- Base resources and pages that panel wrappers extend.
- Widgets or page implementations reused by multiple panel-owned wrappers.

Panel wrappers may be intentionally thin. For example, `Panels/Atmo/Resources/AcceptedFlights/AcceptedFlightResource` can extend the shared accepted-flight resource and only override `getPages()` so Filament registers ATMO-owned page classes and route actions.

This keeps routes and providers easy to scan by panel without copying the real behavior into every role folder.

## Provider Registration

Panel providers remain under `app/Providers/Filament`.

Each provider should import and register classes from its matching `app/Filament/Panels/{PanelName}` folder. Do not register shared resources directly from a provider unless the class is deliberately global and has no panel-owned route surface.

## Rule Of Thumb

Put the route-facing class under the panel folder. Put the reusable implementation under `Shared`.

If ATS, Dispatch, Avsec, Admin, Artisan, ATMO, or Pilot all need the same table or form behavior, keep that behavior shared and expose it through panel-owned wrappers. This keeps ownership visible while preserving one source of truth for policies, buttons, filters, and form logic.
