# Role Access

ProjectEcho uses the `App\Enums\UserRole` enum for user roles. Valid role values are:

- `ARTISAN`
- `ADMIN`
- `ATMO`
- `ATSHQ`
- `AVSEC`
- `DISPATCH`
- `PILOT`

## Access Rules

### ARTISAN

Programmers and maintainers of the application.

- Full access to the admin panel
- Can create, view, update, review, accept, reject, and delete flight plans
- Can update operational flight times

### ADMIN

Application administrators who may not necessarily be programmers.

- Full access to the admin panel
- Can create, view, update, review, accept, reject, and delete flight plans
- Can update operational flight times

### ATMO

Air Traffic Management Officer users.

- Full access only when the user's `station` is `RPUS`
- Can create, view, update, review, accept, reject, and delete flight plans when assigned to `RPUS`
- Can update operational flight times when assigned to `RPUS`
- Cannot access the admin panel when assigned to another station

### ATSHQ

Air Traffic Service Headquarters users in Pasay City.

- Read-only access
- Can view flight plans and related assets
- Cannot create, update, review, accept, reject, or delete flight plans
- Cannot update operational flight times

### AVSEC

Aviation Security users who review manifest, cargo, and related flight information.

- Read-only access
- Can view flight plans and related assets
- Cannot create, update, review, accept, reject, or delete flight plans
- Cannot update operational flight times

### DISPATCH

Flight dispatch users who prepare flight plans and update limited operational times.

- Can create flight plans
- Can view flight plans
- Cannot edit or delete flight plans
- Can enter start-up time on accepted flights
- Can enter engine shutdown time on landed flights
- Cannot update take-off, touchdown, or other operational flight times
- Cannot review, accept, or reject flight plans

### PILOT

Pilot users who file flight plans.

- Can create flight plans
- Can view flight plans
- Cannot directly update an existing database row
- Editing a flight plan creates a new pending flight plan revision and leaves the original row unchanged
- Cannot review, accept, reject, or delete flight plans
- Cannot update operational flight times

## Notes

- Legacy `ATC` role values are normalized to `ATMO`.
- Role comparisons should use the `UserRole` enum or helper methods on `App\Models\User`, not raw role strings.
- Do not document passwords, app keys, tokens, or personal login credentials in this file.
