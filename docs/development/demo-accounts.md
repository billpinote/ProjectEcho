# Development demo accounts

These fictional fixtures are for local software testing only. No generated
credential, identity document, qualification, or organization certificate is valid.

## Run

Set these values in your local environment (never commit a populated `.env`):

```dotenv
APP_ENV=local
ECHO_DEMO_PASSWORD=<choose-your-development-password>
ECHO_DEMO_ENABLED=false
```

Run against your existing, migrated development database:

```sh
php artisan db:seed --class=EchoDemoSeeder --no-interaction
```

Only `local` and `testing` environments are allowed. Missing or blank
`ECHO_DEMO_PASSWORD` fails before records or files are written. No default password
is supplied. This seeder does not run migrations or delete existing data.
The PNG renderer requires the already-declared PHP GD extension with FreeType and
the DejaVuSans font included in the installed DomPDF dependency.

To include these accounts in `php artisan db:seed --no-interaction`, explicitly
set `ECHO_DEMO_ENABLED=true`. Clear/rebuild cached configuration after changing
these environment values if your development installation caches configuration.
The direct class command does not require the opt-in flag.

`DatabaseSeeder` no longer calls the overlapping `AdminUserSeeder`,
`DemoUsersSeeder`, `PilotProfileSeeder`, `StudentPilotSeeder`, or
`OperatorStaffSeeder`. Their standalone entrypoints remain for compatibility;
use `EchoDemoSeeder` for the unified roster. Existing unrelated operator,
named-user, ATS, role-profile, and flight seeders are preserved in the main
pipeline, so the main pipeline can create additional records beyond this roster.

## Account reference

The configured password is used only when an account is first created. No
plaintext passwords are included here. All newly created accounts are active,
with verified email addresses. The six pilot/student accounts have verified demo
KYC documents and approved synthetic signature images.
Every ATMO account has station `RPUS`.

| Email | Name | Role / profile | Operator | Panel | Documents |
|---|---|---|---|---|---|
| pilot1.leiaa@example.com | Adrian Mendoza | PILOT / CPL, ASEL, IR | LEIAA | Pilot | 4 verified sample PNGs + approved demo signature |
| pilot2.leiaa@example.com | Bianca Reyes | PILOT / PPL, ASEL | LEIAA | Pilot | 4 verified sample PNGs + approved demo signature |
| dispatch1.leiaa@example.com | Carlo Navarro | DISPATCH | LEIAA | Dispatch | None |
| operator1.leiaa@example.com | Diana Santos | OPERATOR_STAFF | LEIAA | Dispatch | None |
| student1.leiaa@example.com | Ethan Villanueva | PILOT / SPL training endorsement | LEIAA | Pilot | 4 verified sample PNGs + approved demo signature |
| pilot1.aag@example.com | Gabriel Cruz | PILOT / CPL, ASEL, IR | AAG | Pilot | 4 verified sample PNGs + approved demo signature |
| pilot2.aag@example.com | Hannah Flores | PILOT / PPL, ASEL | AAG | Pilot | 4 verified sample PNGs + approved demo signature |
| dispatch1.aag@example.com | Isaac Ramos | DISPATCH | AAG | Dispatch | None |
| operator1.aag@example.com | Julia Mercado | OPERATOR_STAFF | AAG | Dispatch | None |
| student1.aag@example.com | Kyle Bautista | PILOT / SPL training endorsement | AAG | Pilot | 4 verified sample PNGs + approved demo signature |
| atmo1.rpus@example.com | Lorenzo Garcia | ATMO | — | ATMO | None |
| atmo2.rpus@example.com | Maria Castillo | ATMO | — | ATMO | None |
| admin1.echo@example.com | Nathan Rivera | ADMIN | — | Admin | None |
| avsec1.rpus@example.com | Olivia Torres | AVSEC | — | AVSEC | None |
| avsec2.rpus@example.com | Paolo Fernandez | AVSEC | — | AVSEC | None |

LEIAA is Leading Edge International Aviation Academy; AAG is Alpha Aviation
Group. Existing operators are reused by exact name without changing their data.
New operator certificate references use `TEST-ONLY-LEIAA` / `TEST-ONLY-AAG`.
User affiliations use the canonical `users.operator_id`; the legacy profile
`operator` string stays null. Operator staff also have the corresponding
operator-staff profile and fictional representative authorization. Students keep
the normal SPL restrictions: they prepare for another PIC and cannot authorize
as the flight's PIC. No production authorization or flight workflow is changed.

## Supporting documents and schema limits

Each pilot/student has a fictional license/permit, medical certificate,
qualification record, and non-government profile identity card. All images show
`PROJECT ECHO TEST DATA`, a large `SAMPLE — NOT VALID` banner, and
`FICTIONAL DOCUMENT FOR SOFTWARE TESTING ONLY`. They include the fictional name,
credential number, credential type, issue date, and expiry date, without seals,
signatures, or government-document designs.

Files are linked through `User::kycDocuments()` to the same user as the pilot
profile. They live on the private `local` disk at:

```text
storage/app/private/kyc-documents/echo-demo/{user-id}/license.png
storage/app/private/kyc-documents/echo-demo/{user-id}/medical.png
storage/app/private/kyc-documents/echo-demo/{user-id}/qualifications.png
storage/app/private/kyc-documents/echo-demo/{user-id}/identity.png
```

Existing authenticated KYC download routes allow the active owner or an
Admin/Artisan reviewer. No files are written to the public disk. The private disk
root and these route checks protect access; Windows does not expose POSIX file
visibility consistently. Use normal host access controls for private storage.

The seeder sets both KYC verification fields using the seeder-owned demo Admin,
`admin1.echo@example.com`, as the fixture reviewer and records a development-only
verification audit event. Existing unverified attachments from the earlier demo
seeder are upgraded in place, without duplicate uploads. The account's profile
page displays `KYC documents verified`. Verification is a software fixture state,
not a claim of real-world identity or regulatory review. There is no separate
profile-verification column, and no production credential checks are relaxed.

Each pilot/student also receives a unique PNG displaying their fictional name,
a generated flourish, and `SYNTHETIC SIGNATURE - DEMO ONLY`. The signature is
normalized, submitted, and approved through the existing signature/profile-update
services, with the demo Admin as reviewer. Its completed request remains in
history; it does not wait in the pending-request queue. Version, hash, review,
and approval audit data use the normal application schema. Signature files stay
on the private `local` disk under `pilot-signatures/{user-id}/{version-uuid}.png`.
Existing signature versions, including pending uploads, are never replaced or
automatically reviewed. An approved fixture signature becomes available to the
existing explicit filing-signature consent action. Seeding does not sign existing
flights or packages, and student pilots retain their normal non-PIC restrictions.

The current schema has no license issue-date, home-base, medical-number, medical
class, or profile-completeness columns. The earlier home-base column was renamed
to the legacy operator string; it is not repurposed. Issue dates and fictional
medical identifiers are displayed on the images. License/medical expiry dates
use the existing profile columns. Medical and qualification uploads use supported
KYC type `other`, with distinct identifiers; the identity fixture uses
`company_id`. KYC has no document version column. No new schema is introduced.

## Repeatability and preservation

Accounts match by stable email. A creation audit entry with action
`echo_demo_created` and source `echo_demo_seeder` records ownership. An existing
matching account without that entry is skipped with a warning, including its
password, roles, credentials, affiliations, uploads, and verification state.
Consequently a database containing collisions may have fewer than 15 newly
created demo accounts. Resolve collisions manually if a complete fresh roster is
needed; the seeder never takes over those accounts.

Reruns do not reset passwords, overwrite profiles, renew dates, remove added
qualifications/documents, or replace existing file bytes. Missing generated files
can be restored when their original, unverified fixture attachment still exists.
Replaced or reviewed attachments are preserved. Existing unrelated files at a
new fixture path cause a safe failure instead of an overwrite. New database
records roll back together on failure; only files created by that failed run are
removed. Existing records and files are not deleted. A password-account email
already assigned to another identity causes a safe failure.

The active seeder-owned demo Admin must be available for fixture verification;
a conflicting non-demo account at its email causes a safe failure. Verification
does not take over non-demo accounts, uploaded replacement documents, or signature
versions. Reruns retain existing verification timestamps and signature versions.

## Verification

`tests/Feature/EchoDemoSeederTest.php` covers the roster, relationships,
qualifications, all account logins/panels, SPL restrictions, PNG decoding,
verified document links, approved synthetic signatures, private downloads, reruns, existing-data preservation,
configuration guards, opt-in integration, and rendering-failure rollback. Tests
use the in-memory SQLite database from `phpunit.xml` and fake storage disks.
The seeder uses portable Eloquent operations and no database-specific SQL;
MySQL still requires verification in an installation with a test MySQL server.
