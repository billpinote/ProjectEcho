# Controller Architecture

## Status

This document describes the target structure for the FlightController refactor. Confirm the final classes and route bindings against the implementation before treating the migration as complete.

## Responsibilities

Controllers coordinate HTTP requests. They authorize access, validate input, call services, and return responses.

Business rules and reusable operations belong in domain services. Controllers should not implement QR image drawing, PDF storage management, shared normalization, or complex database mutations.

## Controller Map

Controllers under `app/Http/Controllers/FlightPlans`:

| Controller | Responsibility |
|---|---|
| FlightPlanController | Display the blank form, file an operational flight plan, and display saved flights |
| FlightPlanPreviewController | Store, display, edit, and discard browser-session previews |
| FlightPlanReviewController | Accept or reject flight plans through the ATC review workflow |
| FlightPlanQrController | Display a flight’s QR page and download its QR image |
| FlightPlanScanController | Look up QR payloads, display scanned previews, and prefill forms from QR data |
| FlightPlanPdfController | Download stored PDFs and generate PDF-only flight plans |
| PicAuthorizationPreviewController | Display flight plans reached through PIC authorization handoffs |
| FilingAuthorizationController | Serve authorized package documents/history and explicit self-prepared filing consent |

Other controllers:

| Controller | Responsibility |
|---|---|
| Reports/FlightReportController | Export abbreviated flight reports and post-operations logs as PDFs |
| FlightManifestController | List, display, and save flight manifests |
| ProfileUpdateRequests/ProfileDocumentController | Serve profile-update and KYC documents with access checks |

Consult `routes/web.php` for the actual URLs, HTTP methods, middleware, and route names.

## Request Flow

A typical request follows this sequence:

1. Laravel resolves the route and bound models.
2. Middleware, Form Requests, and policies enforce applicable access rules.
3. The controller obtains validated input and trusted user context.
4. A focused service performs the operation.
5. The controller returns a view, redirect, file, or other HTTP response.

Browser-session reads and writes remain in the HTTP layer. Domain services receive explicit data and models rather than HTTP requests.

Filament pages and actions call the same business services directly instead of invoking controller methods.

## Service Ownership

Flight-plan services live in `app/Domain/FlightPlans/Services`.

| Service | Responsibility |
|---|---|
| FlightPlanSubmissionService | Coordinate creation, preparer/PIC context, submission events, and database-default refresh |
| FlightPlanMutationService | Apply operational transitions and record their associated audit events |
| FlightPlanPdfService | Generate PDFs and manage their filenames, storage lookup, and deletion |
| FlightPlanQrPayloadService | Build, sign, parse, and verify QR payloads |
| FlightPlanQrRenderer | Render QR SVGs and downloadable PNG cards |
| FlightPlanQrLookupService | Resolve verified payloads into lookup results |
| PicAuthorizationService | Enforce PIC eligibility and manage authorization handoffs and decisions |
| FlightManifestService | Save manifests and enforce manifest requirements |
| FilingAuthorizationService | Preserve filing review/decision evidence, signature versions, and material-change invalidation |
| AvsecVerificationService | Verify current signed QR against the server filing, confirm offline PNP procedures for RPUS departures, and preserve permanent submission evidence |

Shared data normalization belongs in
`app/Domain/FlightPlans/Support/FlightPlanDataNormalizer`.

Reusable report queries must be independent of Filament resources so tables and PDF exports can share filtering and visibility rules.

These extracted classes are targets until implemented. Reuse existing services before introducing overlapping alternatives.

## Workflow Boundaries

### Preview and Filing

Saving a preview stores draft data in the browser session. It does not create an operational flight record.

Operational filing applies trusted preparer, pilot, and operator context, creates the record, and records the submission event.

Self-PIC session-preview approval redirects to the prefilled Pilot create page
without creating a record. Its Filament confirmation calls
`FlightPlanSubmissionService::submitSelfAuthorized()` and
`PicAuthorizationService::authorizeSelfFiling()` to capture signature consent,
versioned package PDFs, and Pending submission in one transaction. Representative
filings retain their existing QR authorization path.

Refresh database-generated defaults before signing the submitted flight snapshot.

### PDF-Only Generation

PDF-only generation produces a printable document without creating an operational filing or its QR code.

Keep its eligibility checks separate from operational filing.

### ATC Review

Acceptance and rejection use the shared mutation service.

Controllers retain HTTP validation and responses. Services own the database changes and associated audit events.

PIC authorization and ATC acceptance remain separate decisions.

RPUS departures also require AVSEC confirmation before ATMO acceptance. The AVSEC
panel reuses the flight-plan scanner to load the authoritative server record.
Only active RPUS AVSEC officers may confirm that offline PNP clearance procedures
are complete. Echo does not issue a PNP form or authenticate a physical signature.
Confirmation permanently freezes the approved flight plan, manifest, ordered
persons, cargo, authorization evidence, and approved PDF assets. Revised EOBT and
existing operational lifecycle changes remain possible; rejection never unlocks
the submission. The shared AVSEC register preserves records across shifts.
Database guards cover bulk and quiet writes as well as model/service checks.
The AVSEC migration supports SQLite and MySQL/MariaDB and fails closed on other
database drivers. A future correction/revision workflow is outside this feature.

PIC scanner actions obtain authenticated session evidence at the Filament entry
point. `PicAuthorizationService` consumes that explicit evidence without depending
on the session or login mechanism. See [Authentication and PIC Authorization](authentication.md)
for current audit metadata and future authentication integration boundaries.

See [Unified Filing Authorization](filing-authorization.md) for document registry,
package snapshots, explicit signature consent, material-change classification,
and private PDF retention. Package document access uses the existing flight or
PIC handoff access paths; it does not grant access to standalone profile images.

### QR Processing

Payload verification and image rendering are separate responsibilities.

A valid signature proves the payload’s integrity. Viewing database records or performing mutations still requires the applicable authorization.

Preserve compatibility with supported payload formats and offline snapshot behavior.

## Access Rules

Preserve the distinct access paths:

| Access path | Required handling |
|---|---|
| Authenticated user | Apply policies, role permissions, and operator visibility rules |
| Public browser session | Restrict access to flights explicitly granted to that session |
| Signed offline QR | Verify the payload and apply the scanned-preview access rules |
| PIC handoff | Resolve the handoff and enforce PIC eligibility and flight-access rules |

Do not replace these paths with a single permissive access check.

For file downloads, resolve the requested asset within the authorized flight’s files. A filename alone does not establish access.

## Refactoring Compatibility

Unless a task explicitly changes behavior, preserve:

- Route names, URLs, and HTTP methods.
- Middleware and authorization.
- Session keys and draft lifecycle.
- View variables, redirects, and validation errors.
- Response headers and download filenames.
- QR formats and signing behavior.
- Operator isolation and PIC restrictions.
- Transaction boundaries and audit events.
- PDF generation and retention behavior.

Search for direct callers before moving methods, especially Filament pages and tests.

## Testing Map

Use existing tests as starting points and inspect their assertions before relying on their coverage.

| Area | Relevant existing tests |
|---|---|
| Preview actions | FlightPlanPreviewActionsTest |
| Form validation | FlightPlanRequestTest, FlightPlanIcaoValidationTest |
| QR payloads and prefilling | FlightPlanQrPayloadServiceTest, EditFromQrPrefillTest |
| Operational transitions | FlightOperationsWorkflowTest |
| PIC authorization | PicAuthorizationWorkflowTest, FlightPlanPilotAuthorizationTest |
| Preparer context | FlightPlanPreparerContextFormTest |
| Operator isolation | FlightOperatorIsolationTest |
| Time storage | FlightTimeStorageTest |
| Manifests | FlightManifestTest, FlightManifestPageTest |
| AVSEC confirmation, freezing, shared register, and concurrency | AvsecVerificationTest, AvsecVerificationConcurrencyTest |

Run affected tests after each logical extraction. Add coverage where moved behavior lacks meaningful tests.

## Maintenance

Update this document when controller responsibilities or service ownership change.

Keep route details in the route files and implementation details in code. Avoid duplicating complete method lists, validation rules, or status definitions here.
