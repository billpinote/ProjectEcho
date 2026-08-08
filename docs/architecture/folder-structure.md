# Folder Structure

Project Echo is organized by ownership first, then by framework convention.

## Application Domains

Domain code lives under `app/Domain`.

- `app/Domain/FlightPlans` contains flight-plan business rules, enums, services, and display helpers.
- `app/Domain/Users` contains user/account concepts such as roles.

Keep Eloquent models in `app/Models`. Domain services may use models, but model classes should stay easy to find for Laravel conventions.

## Filament UI

Filament code lives under `app/Filament`.

- `app/Filament/Panels/{PanelName}` contains UI that belongs to one panel only.
- `app/Filament/Shared` contains pages, resources, widgets, schemas, or tables reused by more than one panel.

When adding a new Filament class, ask:

1. Is this only for Pilot, ATMO, ATS, Dispatch, Avsec, Admin, or Artisan?
2. If yes, put it under that panel.
3. If more than one panel uses it, put it under `Shared`.

## Providers

Panel providers remain under `app/Providers/Filament`.

Each provider should explicitly register the pages, resources, and widgets used by that panel. This keeps panel ownership visible during onboarding and code review.

