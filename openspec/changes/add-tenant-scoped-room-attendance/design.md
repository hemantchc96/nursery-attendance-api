# Design

## Context

See proposal.md for motivation and the two delta specs for observable contracts. The current project is a Laravel 12 starter requiring PHP 8.2+, with SQLite defaults, PHPUnit 11 configured for an in-memory SQLite database, one welcome route, and no API registration or attendance domain. No existing specs constrain compatibility. The PDF requests Track A or B, not both; Track A matches this repository. Design is needed because tenancy affects every endpoint and the new data model has a concurrency invariant.

## Goals / Non-Goals

**Goals:** Make tenant scope explicit at every domain query, keep HTTP handling and attendance transitions in small readable units, and enforce one active visit even when requests overlap. Stay within a small take-home implementation using existing dependencies.

**Non-Goals:** Authentication implementation, production identity-to-tenant authorization, frontend work, tenant/nursery/child administration APIs, cross-tenant transfers, reporting, deployment automation, and a generic repository framework.

## Decisions

### 1. Trust the exercise's tenant header and scope explicitly

A route middleware validates X-Tenant-ID before domain access and resolves an existing Tenant. Use immutable request-scoped context (or the resolved request attribute) rather than static state or a process-wide mutable singleton. Tenant lookup is the sole bootstrap query without an established tenant; all subsequent room, child, attendance, and occupant queries include tenant_id. Explicit scoped lookups avoid an unscoped default implicit route binding running before tenant resolution. Do not trust tenant_id supplied in a body. A small shared lookup helper can remove repetition while keeping the constraints visible.

A global Eloquent scope was considered, but explicit predicates are easier to explain and audit for three endpoints and do not require special console/seeder bypasses. Prefer identical generic 404 errors for missing and foreign resources. Force JSON for these endpoints even when Accept is absent; normalize 400/404/409 errors to { message }, and retain Laravel-style { message, errors } for 422 validation.

### 2. Keep the nursery hierarchy and attendance history

Use Tenant (nursery group), Nursery (tenant_id, name), Room (tenant_id, nursery_id, name), Child (tenant_id, name), and CheckIn (tenant_id, room_id, child_id, checked_in_at, nullable checked_out_at). Existing starter User/auth tables need no attendance role. A child belongs to the group; the exercise specifies no enrollment restriction, so allow any room in the same tenant, including another nursery. Record timestamps in UTC and serialize ISO 8601.

Use foreign keys and composite tenant/resource references to prevent inconsistent associations: nurseries/rooms/children expose unique (tenant_id, id) keys; rooms reference (tenant_id, nursery_id); check-ins reference (tenant_id, room_id) and (tenant_id, child_id). Keep direct tenant foreign keys. Index (tenant_id, room_id, checked_out_at) for current occupants. Add a SQLite partial unique index on check-ins (tenant_id, child_id) WHERE checked_out_at IS NULL. This preserves history while enforcing one active visit. Do not cascade deletion of attendance through new management behavior; deletion is outside scope.

An active-row-only table is simpler but loses previous visits and stable checkout IDs. A unique index including nullable checked_out_at does not enforce this invariant in SQLite and must not replace the partial index.

### 3. Reject active attendance rather than silently move a child

Check-in first resolves tenant-owned room and child, then attempts the attendance insert. An existing active visit returns 409 for both same-room duplicates and second-room requests. The database constraint is authoritative; map only its specific unique violation to the conflict response, not arbitrary database failures. This keeps staff intent explicit and avoids silently losing the original room assignment. Automatic movement was considered but requires more transition semantics and communication than the brief needs.

Use a short transaction for the action; avoid reading an active snapshot before a deferred SQLite transaction upgrades to a write. A tenant-owned child/room lookup followed by the insert and targeted unique-violation handling is sufficient. Configure a bounded SQLite busy timeout so concurrent writers can finish and the losing insert observes the unique constraint. Unexpected lock failures remain server errors and must not be misreported as child conflicts.

### 4. Make checkout idempotent

Resolve check-in by tenant and ID, conditionally update checked_out_at only where it is null, then reload it. Return 200 with the original time on repeats, including a race between two checkouts. This permits safe staff retries and prevents an old visit's repeated checkout from closing a newer visit. Rejecting repeats with 409 was considered but adds unnecessary retry handling.

### 5. Preserve the brief's routes and use small HTTP units

Register routes/api.php from bootstrap/app.php with an empty API prefix, providing exactly POST /rooms/{room}/check-ins, POST /check-ins/{id}/check-out, and GET /rooms/{room}/occupants. Keep stateless API middleware and apply tenant resolution to this group; do not use the CSRF-protected web group. Use a Form Request for child_id validation, small controllers calling CheckInChild and CheckOutChild actions, and a focused occupant query. Share check-in serialization via a resource or small mapper. No service-container abstraction layers are necessary.

Return check-in data as { data: { id, room_id, child_id, checked_in_at, checked_out_at } }. Return occupants as { data: [{ child_id, child_name, check_in_id, checked_in_at }] }, ordered by child_id. Filter active attendance and scope joins/relationships to the request tenant. Validate malformed payloads before child lookup; missing tenant context takes precedence because middleware runs first.

### 6. Verify behavior and make the submission reproducible

Use feature tests with RefreshDatabase and tenant-specific fixture factories. Cover lifecycle, completed-visit exclusion, empty rooms, invalid input, missing/unknown headers and resources, same/second-room conflicts, re-entry, idempotent checkout, old-visit retry, tenant A read/write attacks against tenant B, and successive tenant requests. Assert database state after failures. Test the database invariant through a direct duplicate active insert; include a small file-backed SQLite two-connection/process check for overlapping inserts and their API conflict mapping because in-memory tests alone cannot establish concurrency behavior.

Seed two groups with nurseries, rooms, and children for manual isolation demonstrations, and document their identifiers. Replace the generic README with PHP/Composer/SQLite prerequisites; install, environment/key generation, migrate/seed, serve, and test commands; header/JSON examples; decisions and omissions. The API must run without npm. Record actual AI assistance without inventing human review or rejected output.

## Risks / Trade-offs

- [Header spoofing outside the exercise] -> State the upstream authentication/tenant authorization assumption clearly; this API is not a replacement for membership authorization.
- [A future query misses a tenant predicate] -> Centralize tenant resource resolution, scope occupant joins explicitly, and test every endpoint with foreign IDs plus request-context reuse.
- [SQLite-specific partial index and writer serialization] -> Declare SQLite as the supported exercise datastore, verify the constraint on SQLite, and list other-database adaptations as future work.
- [Timebox pressure] -> Keep only three endpoints, minimal seed data, and focused tests; do not add frontend or administrative APIs.
- [Deterministic tests hide contention] -> Add file-backed concurrency verification and document any unresolved lock behavior before claiming overlapping requests work.

## Migration Plan

Add domain migrations in parent-to-child order, including composite foreign keys and the partial active-check-in index. Seed demo tenants only through the documented development seeding command. Validate migrations against a fresh SQLite database and tests against isolated test storage. Rollback reverses new domain tables in dependency order; it removes attendance data, so it is only suitable for the disposable take-home database. No existing attendance data needs backfill.
