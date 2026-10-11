# Proposal

## Why

Nursery staff need to record room arrivals and departures, and managers need an accurate current occupant list. Implement Track A of the supplied take-home exercise while ensuring one nursery group's requests cannot read or change another group's data.

## What Changes

- Add tenant-scoped room check-in, check-out, and current occupant endpoints using the trusted X-Tenant-ID header; authentication is assumed to be handled upstream.
- Model nursery groups as tenants, with nurseries, rooms, children, and attendance records persisted in SQLite.
- Reject check-in with HTTP 409 when a child already has an active check-in, preserving the existing room assignment.
- Validate tenant and resource identifiers, prevent cross-tenant associations, and define consistent JSON responses.
- Add meaningful feature tests for attendance lifecycle, validation, conflicts, and tenant isolation.
- Replace the starter README with runnable setup/test instructions, API examples, assumptions, decision rationale, intentional omissions, future work, and an honest AI-use note.

## Capabilities

### New Capabilities

- `tenant-isolation`: Resolve request tenant context and prevent all attendance API reads and writes from crossing tenant boundaries.
- `room-attendance`: Record check-ins and check-outs and list current room occupants, enforcing one active room per child.

### Modified Capabilities

None; the project has no existing capability specs.

## Impact

The repository currently contains a Laravel 12 / PHP 8.2 starter, SQLite defaults, PHPUnit 11, and no domain models or API routes. Implementation will add API route registration, tenant middleware/context, domain migrations/models, request validation, small attendance actions/controllers, seed data, feature tests, and project documentation. Preserve the exercise's exact endpoint paths without an /api prefix. No frontend, authentication system, management CRUD, new service, or additional runtime dependency is planned; keep delivery proportionate to the 2-3 hour exercise.
