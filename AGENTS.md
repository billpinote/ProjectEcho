# Project Echo — Agent Instructions

## Project Context

Project Echo is a Laravel 12 and Filament 5 application for flight-plan filing, PIC authorization, operational review, manifests, and reporting.

Read the relevant implementation and tests before changing behavior. Treat the code and dependency lockfiles as the source of truth when documentation differs.

## Stack

- PHP requirement: `^8.2`
- Laravel: v12
- Filament: v5
- Livewire: verify the resolved version in `composer.lock`
- PHPUnit: v11
- Tailwind CSS: v4
- Vite: v6
- DomPDF for PDF generation
- Simple QRCode and BaconQrCode for QR rendering
- Laravel Pint for PHP formatting

Do not update dependencies unless the task authorizes it.

## Architecture

Follow `docs/architecture/folder-structure.md`.

- `app/Http/Controllers`: HTTP entry points and responses.
- `app/Http/Requests`: request validation and request-specific authorization.
- `app/Domain/FlightPlans`: flight-plan services, rules, enums, and support classes.
- Other `app/Domain` folders: their corresponding business concepts.
- `app/Models`: Eloquent models and relationships.
- `app/Policies`: resource authorization.
- `app/Filament/Panels/{PanelName}`: panel-owned route-facing resources, pages, and widgets.
- `app/Filament/Shared`: reusable Filament implementations.
- `app/Providers/Filament`: panel providers.
- `resources/views`: Blade templates.
- `tests/Feature` and `tests/Unit`: automated behavior checks.

Keep models in `app/Models`. Reuse existing classes before introducing new abstractions.

Cohesive subfolders within existing application directories are allowed when needed for an authorized task. Ask before introducing a new top-level architecture or changing dependencies.

Treat any synced `sources/` directory as read-only reference material.

## Controller and Service Boundaries

Controllers should:

1. Authorize the operation.
2. Validate input.
3. Invoke focused services or queries.
4. Return a view, redirect, download, or other HTTP response.

Keep business mutations, reusable normalization, QR rendering, PDF storage, and reusable queries outside controllers.

- Prefer constructor dependency injection.
- Keep HTTP requests, responses, redirects, and browser-session management in the HTTP layer.
- Domain services should accept explicit values, models, or typed data rather than HTTP requests.
- Domain services must not depend on Filament resources or pages.
- HTTP and Filament entry points should reuse the same business rules and mutation services.
- Shared report queries should be usable by both Filament tables and PDF exports.
- Avoid large traits, generic base controllers, pass-through classes, and repository layers introduced solely to reduce file length.
- Choose class boundaries by responsibility rather than an arbitrary line limit.

## Flight Controller Refactoring

When splitting `FlightController`, group endpoints by workflow:

- Flight-plan form, submission, and saved-flight display.
- Session preview creation, display, editing, and discard.
- ATC acceptance and rejection.
- QR display and image downloads.
- QR lookup, scanned previews, and edit-from-QR.
- Stored PDF downloads and PDF-only generation.
- PIC authorization handoff previews.
- Report PDF exports.

Use `app/Http/Controllers/FlightPlans` and `app/Http/Controllers/Reports` when implementing this split.

Keep `FlightManifestController` separate.

Reuse and extend existing flight-plan services before adding alternatives. Search for direct callers, including Filament pages and tests, before moving or deleting methods.

## Behavior and Compatibility

During refactoring, preserve unless explicitly authorized to change:

- Route names, URLs, and HTTP methods.
- Status codes, redirects, validation errors, and response headers.
- View variables and download filenames.
- Session keys and preview behavior.
- QR payload formats, signing, verification, and compatibility.
- Policy checks and operator visibility restrictions.
- Database transaction boundaries and audit events.
- Existing PDF generation and retention behavior.

Refactor incrementally and verify each logical extraction.

Do not combine structural refactoring with unrelated behavior changes.

## Flight-Plan Workflow Rules

Preserve these distinctions:

- Saving a session preview does not create an operational flight record.
- Filing an operational flight plan requires the appropriate user permissions and preparer/PIC context.
- PDF-only generation does not create an operational filing or its QR code.
- PIC authorization and ATC acceptance are separate decisions.
- A pending or declined PIC authorization must retain its existing access and workflow restrictions.
- Operator and preparer relationships must come from trusted application context where required.

Use the existing mutation services for status transitions and their audit events.

Keep related database mutations and audit records atomic where the workflow requires it. Do not assume filesystem writes participate in database transactions.

Refresh database-generated defaults, including revision information, before signing a newly submitted flight snapshot.

Inspect the current status enum and transition rules rather than relying on a hard-coded status list in documentation.

## Authorization and QR Access

Keep these access paths explicit:

- Authenticated access through policies and role permissions.
- Public access granted to specific flights through the browser session.
- Signed offline QR snapshot previews.
- PIC authorization handoff access.

Successful QR verification establishes payload validity. It does not automatically authorize viewing every database record or performing mutations.

Preserve existing authorization checks when extracting helpers or services.

Resolve requested PDF assets within the authorized flight’s files. Do not treat a supplied filename or token alone as proof of access.

## Validation and Normalization

Use Form Requests for HTTP validation where appropriate.

Reuse the existing ICAO rules in `app/Domain/FlightPlans/Rules`.

Centralize normalization shared by HTTP forms and Filament, while preserving intentional differences between entry points.

- Use the existing time helpers and tests for storage and display conventions.
- Distinguish UTC clock times from durations such as endurance and total EET.
- Avoid implicit server-timezone conversions.
- Preserve minute precision and existing field formats.
- Normalize numeric inputs before persistence.
- Apply uppercase normalization only to fields whose established contract requires it.
- Keep `other_information` tag parsing and formatting consistent across forms, previews, and QR snapshots.
- Do not overwrite trusted operator or pilot context with untrusted submitted values.

## Filament Ownership

Panel providers should register classes from their corresponding panel folders.

Route-facing resources and pages belong to the panel that owns them. Reusable implementations belong in `app/Filament/Shared`, with panel wrappers where required for routing.

Artisan is the technical maintenance panel. Do not duplicate operational workflows there. Use the real operational panels when supporting or testing operational workflows.

For Filament 5:

- Form fields: `Filament\Forms\Components`.
- Layout components: `Filament\Schemas\Components`.
- Schema utilities: `Filament\Schemas\Components\Utilities`.
- Infolist entries: `Filament\Infolists\Components`.
- Actions: `Filament\Actions`.
- Use `Filament\Support\Icons\Heroicon` where appropriate.
- Preserve inherited property types when overriding pages, resources, and widgets.
- Set file visibility and layout column spans explicitly when required.

Use the installed version’s APIs. Consult official version-specific documentation when uncertain.

## PHP Conventions

Follow neighboring code and existing naming conventions.

- Use descriptive class, method, and variable names.
- Add explicit parameter and return types where practical.
- Use constructor property promotion when appropriate.
- Use braces for control structures.
- Prefer PHPDoc for useful type information, including array shapes.
- Add comments for non-obvious decisions rather than restating code.
- Preserve existing enum cases and backing values.
- Avoid unrelated cleanup in focused changes.

## Laravel Conventions

- Prefer named routes and `route()` for application links.
- Use Eloquent relationships and existing query scopes.
- Use policies for resource authorization.
- Prefer existing factories and factory states in tests.
- Use Artisan generators when available and suitable.
- Pass `--no-interaction` to commands that support it.
- Inspect command help when options are uncertain.

Middleware and exception configuration belongs in `bootstrap/app.php`. Application providers are registered in `bootstrap/providers.php`.

When modifying database columns, preserve required existing attributes.

Do not create or modify database records through Tinker unless authorized by the task. Prefer factories and tests for verification.

Do not run destructive database commands against an unverified database.

## Testing

Use PHPUnit classes and `Livewire::test()` for Filament/Livewire tests.

- Run relevant existing tests for every behavior-affecting change or refactor.
- Add or update tests when behavior changes or relevant coverage is missing.
- Existing behavior tests can verify a pure extraction; do not add tests that merely mirror implementation details.
- Cover meaningful success, validation failure, authorization failure, and edge cases affected by the change.
- Authenticate with the appropriate role and panel context for Filament tests.
- Do not convert unrelated tests between frameworks.
- Do not delete tests without explicit authorization.

For flight-plan refactoring, select relevant coverage for:

- Session previews and PDF-only generation.
- QR parsing, signing, lookup, and scanned previews.
- Flight submission and operational transitions.
- PIC authorization.
- Operator isolation and policy enforcement.
- Time storage and normalization.
- Manifest requirements and audit events.

Run focused tests after each logical step. Broaden testing when shared changes or failures justify it.

Commands:

    php artisan test --compact
    php artisan test --compact tests/Feature/FlightPlanPreviewActionsTest.php
    php artisan test --compact --filter=TestName

Check `phpunit.xml` and test setup before running database-backed tests. Verify that tests use an isolated database.

If tooling is unavailable, report what could not run and why. Do not claim checks passed without running them.

## Formatting and Assets

After modifying PHP, run the installed Pint formatter on affected files or the dirty working tree:

    php vendor/bin/pint --dirty

Inspect the installed formatter’s help before using version-specific options.

For frontend changes, run the relevant build when available:

    npm run build

Use `npm run dev` or `composer run dev` only when a development server is needed. Avoid starting duplicate long-running processes.

## Documentation and Instruction Maintenance

Create documentation files only when requested. Update existing documentation when the task includes documentation changes.

Keep project instructions consistent with:

- `docs/architecture/folder-structure.md`
- `docs/architecture/filament-panels.md`
- `docs/copilot-instructions.md`

Avoid duplicating changing implementation details such as exact file lengths, test counts, field counts, or full status lists.

See `docs/architecture/controllers.md` for controller responsibilities,
service ownership, access boundaries, and relevant tests. Keep it aligned
with the implemented structure when changing those boundaries.

Do not document proposed classes as existing until they are implemented.

## Working Style and Completion

Proceed with routine, reversible work within the authorized task.

Ask for clarification when a missing decision materially affects behavior, security, schema, dependencies, or scope.

Before finishing:

- Review the diff for accidental changes.
- Run appropriate tests and formatting.
- Report what changed and why.
- List the checks actually run.
- Identify remaining failures, limitations, or follow-up work.