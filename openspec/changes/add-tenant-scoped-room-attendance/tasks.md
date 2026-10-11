# Tasks

## 1. Domain storage and reproducible data

- [x] 1.1 Add Tenant, Nursery, Room, Child, and CheckIn migrations/models with tenant IDs, composite foreign keys, timestamps, and occupant index; verify fresh SQLite migrations and tests rejecting cross-tenant room/nursery and attendance associations.
- [x] 1.2 Add the SQLite partial unique index for active attendance; verify a second active insert for the same tenant/child fails, completed history permits re-entry, and different children can be active together.
- [x] 1.3 Add tenant-consistent factories and seed two demo groups with nurseries, rooms, and children; verify seeded relationships and document setup/seed commands and demo identifiers in the README.

## 2. Tenant context and HTTP foundation

- [x] 2.1 Register stateless API routes with an empty prefix and tenant middleware; verify route:list exposes the three exact exercise paths and no CSRF token is required for POST requests.
- [x] 2.2 Resolve and validate X-Tenant-ID into immutable request context and add explicit tenant-scoped resource lookup; verify missing/malformed headers return 400, unknown tenants return 404, and requests never reuse earlier tenant context.
- [x] 2.3 Normalize attendance endpoint errors to JSON and validate positive integer child_id using a Form Request; verify errors remain JSON without Accept, invalid payloads return 422 with errors.child_id, and foreign/nonexistent resources share a generic 404 response.
- [x] 2.4 Document the tenant header trust assumption, lack of auth implementation, exact endpoint paths, and header/validation errors; verify README examples match the registered routes and tested responses.

## 3. Check-in and active-room conflicts

- [x] 3.1 Implement a small CheckInChild action, controller, and shared response mapper using server timestamps and tenant-owned resources; verify HTTP 201 response fields, stored tenancy, missing room/child 404, and no writes after invalid requests.
- [x] 3.2 Map the specific active-attendance unique violation to HTTP 409 and configure bounded SQLite writer waiting; verify duplicate same-room and second-room requests, including another nursery, preserve the original active visit.
- [x] 3.3 Add file-backed SQLite overlapping-write verification with independent connections/processes and test HTTP conflict translation; verify only one active insert succeeds, the loser produces the defined conflict, and unrelated database failures are not masked as 409.
- [x] 3.4 Add tenant A tests targeting tenant B's room and child plus payload tenant_id override attempts; verify 404 for foreign resources and unchanged attendance for both tenants.
- [x] 3.5 Document check-in JSON examples, the rejection choice and rationale, and the SQLite concurrency constraint; verify examples and claimed behavior match the feature and constraint checks.

## 4. Checkout and current occupants

- [x] 4.1 Implement a tenant-scoped CheckOutChild action/controller with a conditional timestamp update and idempotent response; verify initial checkout, unchanged repeated timestamp, 404 for missing/foreign check-ins, and no tenant B mutation by tenant A.
- [x] 4.2 Implement the tenant-scoped active occupant query and small controller; verify response fields/order, empty array, exclusion of completed visits and other rooms/tenants, and 404 for missing/foreign rooms.
- [x] 4.3 Test the complete check-in/list/checkout/list/re-entry lifecycle and old-checkout retries; verify re-entry creates a new visit and retrying the old checkout leaves that visit active.
- [x] 4.4 Document checkout/occupant request and response examples plus idempotent retries; verify a manual demo sequence produces the documented active and empty results.

## 5. Submission and integration verification

- [x] 5.1 Complete README prerequisites, run/test commands, assumptions, intentional omissions, future work, and an accurate AI-use statement; verify every requested submission topic from the PDF is covered and no unperformed human review is claimed.
- [x] 5.2 Run the full PHPUnit suite and Laravel Pint check; verify all attendance, validation, isolation, migration, and concurrency checks pass and summarize any material limitation.
- [x] 5.3 Follow README setup and API examples against a fresh disposable SQLite database without npm; verify all three endpoints work and tenant A cannot read or mutate tenant B, without modifying the user's development database.
