# Authentication and PIC Authorization

## Implemented authentication

Authentication remains password-based. `config/auth.php` configures the `web`
session guard and the custom `auth-account` user provider registered by
`AppServiceProvider`. `AuthAccountUserProvider` first resolves password accounts
from `auth_accounts` and checks their `password_hash`; it falls back to the
existing user password when no applicable account hash is available. These are
existing compatibility paths, not separate login screens.

Filament panels use their existing login page through `ConfiguresEchoPanel`.
The installed login implementation validates credentials, calls `attemptWhen`
with `User::canAccessPanel()`, and regenerates the session on success. The shared
panel middleware includes session authentication and CSRF protection. Role,
active-account, and applicable station restrictions remain in the existing user
and policy methods. Session storage and cookie settings remain in
`config/session.php`; authentication evidence does not change them.

There is no passkey, TOTP, enrollment, recovery-code, or additional challenge
implementation in this change. No dependencies or credential tables are added.

## Authentication versus authorization

Authentication identifies the actor. PIC authorization is an explicit decision
about a particular saved flight revision. An authenticated user is not thereby
eligible to become its PIC, accept it as ATMO, or apply a signature image.

`PicAuthorizationService` continues to own QR/handoff validation, operator access,
eligible verified pilot credentials, preparer restrictions, current revision
checks, approval, and decline. It consumes explicit users and optional
`App\Auth\AuthenticationEvidence`; it does not read passwords, browser sessions,
Filament state, or HTTP requests.

The existing same-operator handoff is intentional: an eligible pilot can become
PIC of a filing prepared by another user. This is distinct from impersonating
another PIC. Actor identity is always the user making the decision; authentication
evidence for a different actor is rejected. Existing cross-operator and preparer
restrictions remain in force.

## Evidence boundary and audit records

The Pilot scanner actions construct `AuthenticationEvidence::fromSession()` from
the current Filament session guard. The Dispatch scanner inherits those actions.
The evidence constructor is private; request fields cannot select a method or
assert a verification result. The session factory requires an active persisted
Echo user and the existing `AuthAccountUserProvider`.

The only implemented evidence identifier is `password_session`. This means an
existing authenticated session under the current password-only configuration,
including supported session restoration. It does not mean that the password was
entered again for the PIC action. Its audit representation is:

```json
{
  "authentication": {
    "user_id": 123,
    "method": "password_session",
    "freshly_verified": false
  }
}
```

The service checks the evidence actor before acting. PIC approval/decline events
and the related database mutation are recorded in the same database transaction.
Approval PDF generation remains outside that transaction: a filesystem/rendering
failure cannot roll back a committed decision, and now cannot remove its audit
evidence. No PDF layout or signature application is changed.

`pic_authorization_method = QR` continues to describe the authorization channel;
it must not be repurposed to describe authentication. Authentication metadata
uses the existing `flight_plan_events.new_values` JSON, so no migration is needed.

Existing trusted in-process callers may omit the optional evidence argument.
Those calls retain their authorization checks and do not invent authentication
metadata. This compatibility path is not a new public endpoint. All browser PIC
decision actions supply guard-derived evidence. Future entry points must do the
same with genuinely verified evidence; a supplied User alone does not prove a
browser authentication ceremony occurred.

Historical records without the JSON key remain valid under the existing rules.
Absence means authentication evidence was not captured; it must not be inferred
as password verification or backfilled as a new ceremony.

## Current evidence completeness

| Evidence | Existing storage / limitation |
|---|---|
| Flight ID | Flight primary key and event `flight_id` |
| PIC user ID | `pic_authorized_by_user_id`, PIC relationship, event actor |
| Authorized revision | `pic_authorized_revision`; decision event revision |
| Authorization timestamp | `pic_authorized_at`; event `created_at` |
| Authentication method | New browser decision event JSON; unknown for older/trusted calls |
| Manifest revision | Not captured; manifests have no authorization revision binding |
| Signature version | Not captured; profile verification is separate from document consent |

The existing profile signature architecture remains authoritative:
`User::approvedSignature()` selects the active version; `PilotSignatureService`
checks file hashes and manages review; prior versions are preserved. No signature
is applied merely because the user is authenticated. Manifest signing and
signature-version binding require a separate approved workflow, including how
post-authorization manifest edits affect consent. Existing manifest editing and
accepted PDF snapshots are unchanged.

## Future passkey and TOTP integration (not implemented)

Reserve the identifiers `passkey` and `totp` for evidence produced by real
verification. There are no factories, adapters, or success stubs for either now.

When libraries are selected, extend the evidence boundary with narrowly scoped
factories called only after server-side verification. Keep verified actor identity
and freshness semantics explicit; do not accept a client-supplied method string.
If the session guard later supports multiple methods, update its provenance
tracking and `fromSession()` before enabling them: the current factory assumes
the present password-only provider and must not label a passkey session as a
password session.

For passkeys, verification must include the library's challenge, origin, RP ID,
credential ownership, user-presence/user-verification, and replay protections.
Only a successful result may issue `passkey` evidence and establish a session or
provide action-specific verification. Reuse the PIC business service afterward.

TOTP is usually an additional factor rather than a complete identity provider.
Decide whether it augments password/session evidence or provides a separately
scoped step-up result. Enrollment requires verified confirmation; verification
needs throttling, controlled clock skew, and time-step replay protection. Merely
having an authenticator enrolled must never produce `totp` evidence. A future
evidence format may need factor composition, ceremony time, expiry, and action
binding; do not overload the current session observation with those claims.

## Proposed future schemas (not migrations)

Finalize schema names, types, lengths, and lifecycle constraints only after
selecting the implementation libraries. These are design considerations:

| Concept | Candidate data and constraints |
|---|---|
| Passkey credentials | User FK; globally unique credential ID with library-compatible binary encoding; public key; signature counter and backup flags as supported by the library; transports; label; created/last-used/revoked times. Store no private keys or reusable challenges. |
| TOTP authenticators | User FK; encrypted secret using managed application keys; library-compatible algorithm/digits/period; confirmation/revocation times; last accepted step with atomic replay protection. Define multi-device limits explicitly. |
| Recovery mechanisms | User FK; individually hashed high-entropy recovery codes; issued/consumed/revoked times; atomic one-use consumption. Require reauthentication for rotation and audit recovery without recording codes. |

Existing `auth_accounts` can remain the password/provider identity mapping.
Do not force passkey counters or encrypted TOTP secrets into `password_hash` or
store structured credentials in identifier fields. Separate credential records
are likely appropriate once their actual lifecycle and library contracts are
known. Keep secrets out of serialization, logs, sessions, events, and QR payloads.

## Security and offline compatibility

QR payload cryptographic verification proves payload integrity, not the identity
or authority of the scanning actor. Keep existing QR formats and revision checks.
Handoff resolution and PIC mutations still require the live application/database;
this preparation does not introduce offline authorization.

Offline snapshot previews must not manufacture session evidence, fetch private
signature images from untrusted identifiers, or mutate operational records.
Any future offline decision protocol needs explicit device trust, signed and
action-bound evidence, replay protection, revocation handling, and server-side
reconciliation before operational acceptance. WebAuthn's local authenticator and
TOTP's local code generation alone do not provide that protocol.

Maintain existing signature image access restrictions and audit history. Future
signed documents must explicitly bind the authorized signature and document
versions. Existing separate concerns remain: manifest edits before ATMO acceptance
are allowed, and flight-plan material-edit/revision invalidation must be assessed
before adding document signatures. No new guarantees about those workflows are
introduced by authentication metadata.

## Verification

`PasswordAuthenticationTest` exercises the actual Filament login with password
accounts and legacy user hashes, failed credentials, panel restrictions, and
guest/inactive evidence rejection. `PicAuthorizationWorkflowTest` covers browser
evidence, actor mismatch, legacy calls, and existing QR approval/decline rules.
Retain the existing panel-access, filing/acceptance, operator-isolation, logout,
and pilot-signature suites as regression coverage. Tests use the SQLite in-memory
database configured in `phpunit.xml`; do not run schema changes against a live DB.
