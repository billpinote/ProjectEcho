# Unified Filing Authorization and Verified Signatures

## Implemented scope

A filing uses the existing flight ID as its filing ID. `FilingAuthorization`
records a complete, versioned review package for that flight. The existing
`PicAuthorizationService` still owns PIC eligibility, operator access, QR/handoff
checks, approval, and decline. `FilingAuthorizationService` adds package evidence,
signature selection, and centralized change detection; it does not implement a
second PIC eligibility policy.

The previous authentication milestone is commit `cc84120`. Password login and
`AuthenticationEvidence` remain unchanged. This milestone introduces no passkeys,
TOTP, AVSEC form, or authentication challenge.

## Package lifecycle

1. A current QR scan creates the existing revision-bound handoff and, in the same
   transaction, a `pending` package containing every currently attached document.
2. Scanner actions list all included documents. The existing flight-plan preview
   adds package review links; each linked preview renders the preserved snapshot,
   not a newly selected live manifest.
3. The PIC explicitly chooses unsigned authorization, authorization with the
   verified signature image, or decline. One decision covers the complete package.
4. With the flight and document rows locked, the service verifies that the reviewed
   package fingerprint still matches. It resolves fresh PIC credentials, optionally
   verifies and captures the approved signature version, and saves the decision,
   authorized snapshots, preserved PDFs, and activity events atomically.
   An attached manifest must pass the existing completeness/PIC/person-count/cargo
   checks before that decision commits. A flight-only package is allowed; attaching
   the manifest later requires renewed review. ATMO's existing manifest requirement
   remains in place.
5. The package becomes `authorized` or `declined` and cannot be edited or deleted
   through Eloquent. SQLite triggers also protect decided evidence and all package
   deletion from bulk SQL operations. No historical records are backfilled.

Review snapshots preserve what was presented before the decision. Authorized
snapshots additionally preserve the verified PIC identity and credential fields
assigned by the existing PIC workflow. The action explains that the reviewing
pilot becomes PIC; authorization cannot select another user as its actor.
After PIC assignment, the service checks the entire document set again. Only
the existing trusted PIC credential fields may differ from the reviewed content;
a newly appearing document or other content change aborts the decision.

The flight row still stores the current compatibility authorization fields.
`pic_authorization_method = QR` remains the handoff channel, distinct from the
authentication method. Package authentication is the existing guard-derived
`password_session` evidence with `freshly_verified: false`. Trusted internal calls
may omit authentication evidence, in which case it remains unknown rather than
being manufactured. Browser decisions always provide session evidence.

Creating another handoff rotates the existing token and captures another package.
Older pending packages remain historical requests; they do not authorize anything.
The latest package must be authorized and match the current document fingerprints
before it can supply a current signature or allow ATMO acceptance.

## Document registry and fingerprints

`FilingDocumentRegistry` is the single registry for document membership, snapshots,
and snapshot PDF rendering. It currently registers:

- `flight_plan`: flight ID, integer flight revision, and filed content.
- `passenger_cargo_manifest`: the existing combined CAAP passenger/cargo manifest,
  manifest ID, a content-addressed revision, and manifest/person/cargo content.

There is no separate cargo-only model or PDF workflow to duplicate.

Canonical JSON recursively sorts associative keys, preserves list order, and uses
explicit Unicode/slash/number encoding flags. SHA-256 fingerprints cover document
types, IDs, revisions, and content. Manifest person order matters; database person
row IDs do not, so replacing identical ordered rows does not change consent.
Persisted date, time, Boolean, and decimal casts provide the existing field
representation. This does not introduce timezone conversions or alternate ICAO
normalization.

The database stores encrypted review/authorized snapshot arrays and encrypted
PDF byte arrays, alongside fingerprints and decision metadata. Snapshots do not
depend on live relationships for reconstruction. Every preserved PDF has its own
SHA-256 and is returned with private/no-store headers after access checks.

Future document integrations register both a snapshot callback and a renderer via
`FilingDocumentRegistry::register()`. They automatically participate in package
membership, fingerprint comparison, decisions, and preservation. A future AVSEC
implementation must provide its actual model, revision contract, permissions,
and renderer before registration. No AVSEC implementation or unused table exists.
Changing a registered document's fingerprint contract requires an explicit schema
version/migration plan; do not reinterpret historical snapshot arrays.

## Signature consent and preservation

Unsigned authorization remains supported. Logging in, filing a flight, opening a
preview, or approving a profile signature does not apply a signature image.
The scanner offers a separate explicit signed-authorization action. Self-prepared
flights retain their ordinary filing path and expose an explicit review/signing
action from the saved flight preview. Creating that review request makes the
package pending until the PIC decides it; opening it does not sign it.

Electronic signing requires the active approved signature to belong to the PIC.
`PilotSignatureService::verifiedBytes()` verifies its stored bytes before the
decision. The package preserves its ID, version, and SHA-256. Missing/pending
signatures produce a clear validation message; unsigned authorization remains
available. Integrity failures abort the transaction without consuming the handoff.

Replacing the profile signature does not change older packages. Historical
signature bytes are selected by the captured ID/version/hash, including a version
subsequently marked superseded. Preserved PDF copies remain reproducible even
when profile state or rendering templates later change. Historical copies are
evidence of the original decision, not proof that the current filing is authorized.

The flight-plan signature uses the existing pilot name/signature cell without
moving its name or credentials. FPDI/TCPDF overlays the signature in the existing
manifest signature area above the printed name. The same captured version supplies
saved previews, regenerated PDFs, and accepted manifest snapshots. No image is
rendered on a current unsigned, declined, or materially changed filing.

This is the display of a verified handwritten signature image and recorded PIC
consent. It is not a cryptographic PDF digital signature.

## Material versus operational changes

The initial classification was confirmed for this implementation:

| Change | Effect |
|---|---|
| Filed flight-plan fields, PIC/representative context, route, proposed departure, aircraft, persons on board, remarks, and qualifications | Material |
| Any attached manifest content, ordered person entries, cargo, attachment, or removal | Material |
| Status, ATMO review/receipt metadata, cancellation metadata, actual movement times | Operational |
| Revised operational EOBT through the existing delay workflow | Operational |
| Authorization bookkeeping and lineage metadata | Managed by the authorization/revision workflow, not treated as filed content |

The exact field classification is centralized in `FilingDocumentRegistry`.
Unknown future document types must define their own material snapshot before
registration; do not infer additional aviation rules from this implementation.

`FilingAuthorizationService::mutate()` locks the parent flight and compares the
registered document set before and after a mutation. Flight, manifest, and person
model saves participate, including quiet saves. Manifest service row replacement
is grouped into one outer mutation so a no-op save does not invalidate consent.
Material changes increment the filing's flight revision, clear current approval
and handoff fields, return the filing to awaiting PIC, and record an invalidation
event. Prior packages are never overwritten. Operational updates leave the
fingerprint and authorization intact.

Direct bulk/query-builder writes bypass Eloquent mutation hooks and must not be
used as application document mutation entry points. Fingerprints are recomputed
on current-authorization checks, so such changes still fail closed at signing,
preview signature resolution, and acceptance. All supported document writers and
PIC decisions coordinate through the parent flight lock; snapshots also lock
manifest/person rows during transactionally protected reads.

## Access, history, and storage

Existing flight policies and operator restrictions remain in force. A pending
package's document preview also supports the existing same-operator PIC handoff
access path, with a valid current token and eligible PIC. Historical packages
are available to authorized flight viewers and to their original PIC under the
operator restriction. A document type must actually belong to the requested
package. A token or document filename alone is insufficient authorization.

New flight-plan PDF caches use the private local disk, separated by flight ID,
package ID, and content fingerprint. Requested filenames resolve only within the
authorized flight's current cache. Existing unsigned public PDFs remain supported
as a legacy fallback for flights without package history. No signature image or
signature-bearing PDF is newly written to public storage. Existing private image
routes retain their original owner/reviewer restrictions.

Package history is linked from saved previews and PIC activity events. Files with
package history cannot be deleted; cancellation and correction retain the audit
trail. Correct and Resubmit creates a new flight with its existing revision
lineage and no inherited package decision/signature. Existing accepted filing
locks and encrypted manifest PDF snapshots remain in effect.

## Deployment and limitations

The additive `create_filing_authorizations_table` migration preserves existing
flights, signatures, and events. Run it through the normal deployment process;
development/testing here uses SQLite in memory and does not migrate the live DB.
Historical authorizations without package evidence retain legacy behavior until
a new package request is made. They do not acquire electronic signatures.

Package evidence and its preserved PDFs commit together. The subsequent private
flight-plan cache write remains a filesystem operation outside the transaction;
if it fails, durable evidence is retained and can be downloaded through package
history. Database encryption requires retaining the application encryption key
and including package records in the existing backup/recovery process.

Offline signed QR snapshots remain compatible and unchanged. They prove QR
payload integrity, not PIC identity or consent to a manifest. A live package
review and server-side decision are still required. This change does not add
offline signing or synchronization conflict resolution.

## Verification

`UnifiedFilingAuthorizationTest` covers document combinations, signed/unsigned
consent, missing/unapproved/tampered signatures, signature replacement, material
and operational edits, no-op saves, stale packages, bulk-change detection, private
access, self-prepared consent, immutable evidence, rendering rollback, and Correct
and Resubmit. Existing password, QR, PIC, filing, manifest, acceptance, isolation,
and signature-management tests remain regression coverage. Synthetic flight and
manifest PDFs are rendered for visual inspection without creating live records.

## Changed-file index

Paths below are relative to the repository root. New files are identified with
`(new)`; all other listed files extend existing implementations.

| Directory | Files |
|---|---|
| `app/Models/` | `FilingAuthorization.php` (new), `Flight.php`, `FlightManifest.php`, `ManifestPerson.php` |
| `app/Domain/FlightPlans/Services/` | `FilingAuthorizationService.php` (new), `PicAuthorizationService.php`, `FlightManifestService.php`, `FlightPlanPdfService.php`, `ManifestPdfService.php` |
| `app/Domain/FlightPlans/Support/` | `FilingDocumentRegistry.php` (new), `ManifestPdfLayout.php` |
| `app/Http/Controllers/FlightPlans/` | `FilingAuthorizationController.php` (new), `FlightPlanController.php`, `FlightPlanPdfController.php`, `FlightPlanQrController.php`, `PicAuthorizationPreviewController.php` |
| `app/Filament/` | `Panels/Pilot/Pages/ScanAuthorizationQr.php`, `Shared/Resources/Flights/Pages/CreateFlight.php` |
| Application wiring | `app/Policies/FlightPolicy.php`, `app/Providers/AppServiceProvider.php`, `routes/web.php` |
| `resources/views/` | `flightplan/filing-history.blade.php` (new), `flightplan/pdf.blade.php`, `flightplan/pic-authorization-required.blade.php`, `filament/pages/import-scan-qr.blade.php` |
| `database/migrations/` | `2026_11_01_000001_create_filing_authorizations_table.php` (new) |
| `tests/Feature/` | `UnifiedFilingAuthorizationTest.php` (new), `FlightManifestTest.php`, `FlightPlanPreviewActionsTest.php`, `FlightPlanRefactorTest.php`, `PicAuthorizationWorkflowTest.php` |
| `docs/architecture/` | `filing-authorization.md` (new), `authentication.md`, `controllers.md` |
