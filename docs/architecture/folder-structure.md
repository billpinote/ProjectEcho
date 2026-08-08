# Folder Structure

Project Echo is organized by ownership first, then by framework convention.

## Application Domains

Domain code lives under `app/Domain`.

- `app/Domain/FlightPlans` contains flight-plan business rules, enums, services, and display helpers.
- `app/Domain/Users` contains user/account concepts such as roles.

Keep Eloquent models in `app/Models`. Domain services may use models, but model classes should stay easy to find for Laravel conventions.

## Filament UI

Filament code lives under `app/Filament`.

- `app/Filament/Panels/{PanelName}` contains the route-facing pages, resources, and widgets registered by that panel provider.
- `app/Filament/Shared` contains reusable implementations such as base resources, base pages, widgets, schemas, tables, and common behavior.

When adding a new Filament class, ask:

1. Which panel provider registers this page, resource, or widget?
2. Put that route-facing class under `app/Filament/Panels/{PanelName}`.
3. If the behavior is reused across panels, put the implementation under `app/Filament/Shared` and make the panel class extend it.
4. If a new panel provider is added, add the matching `Panels/{PanelName}` folder at the same time.

Current panel folders are `Admin`, `Artisan`, `Atmo`, `Ats`, `Avsec`, `Dispatch`, and `Pilot`.

## Providers

Panel providers remain under `app/Providers/Filament`.

Each provider should explicitly register the pages, resources, and widgets used by that panel. This keeps panel ownership visible during onboarding and code review.

Providers should import from their matching panel folder. Shared classes should stay behind panel-owned wrappers when they generate Filament routes.
