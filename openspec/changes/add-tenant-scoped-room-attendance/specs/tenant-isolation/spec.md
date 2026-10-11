# Spec Delta

## Purpose

Keep each nursery group's attendance data private by resolving tenant context for every request and constraining all resource access to that tenant.

## ADDED Requirements

### Requirement: Tenant header resolution
Every attendance API request MUST supply X-Tenant-ID as a positive integer identifying an existing tenant. Missing or malformed headers SHALL return JSON HTTP 400; an unknown tenant SHALL return JSON HTTP 404. Invalid context MUST cause no attendance mutation. Authentication and permission to select the supplied tenant are assumed to be established upstream.

#### Scenario: Valid tenant
- **WHEN** a request supplies an existing tenant's positive integer ID
- **THEN** resource access uses that tenant as its scope

#### Scenario: Missing or malformed tenant
- **WHEN** a request omits X-Tenant-ID or supplies a blank, non-integer, zero, or negative value
- **THEN** it returns JSON HTTP 400 and changes no attendance data

#### Scenario: Unknown tenant
- **WHEN** a request supplies a well-formed ID that does not identify a tenant
- **THEN** it returns JSON HTTP 404 and changes no attendance data

### Requirement: Tenant scoped resource reads and writes
Room, child, check-in, and occupant access MUST be scoped to the resolved tenant. Foreign and nonexistent room or check-in IDs SHALL return the same JSON HTTP 404 shape. Foreign and nonexistent child IDs in a valid check-in payload SHALL return the same JSON HTTP 404 shape. Rejected requests MUST leave every tenant's attendance unchanged.

#### Scenario: Foreign room read
- **WHEN** tenant A requests occupants of tenant B's room
- **THEN** the response is HTTP 404 with no occupant data

#### Scenario: Foreign room write
- **WHEN** tenant A attempts to check a child into tenant B's room
- **THEN** the response is HTTP 404 and no check-in is created

#### Scenario: Foreign child write
- **WHEN** tenant A attempts to check tenant B's child into tenant A's room
- **THEN** the response is HTTP 404 and no check-in is created

#### Scenario: Foreign attendance write
- **WHEN** tenant A attempts to check out tenant B's active check-in
- **THEN** the response is HTTP 404 and tenant B's check-in remains active

#### Scenario: Own room read
- **WHEN** tenant A lists occupants of its own room while tenant B has active attendance elsewhere
- **THEN** only tenant A's children and check-in identifiers are returned

### Requirement: Request context does not leak
Tenant scope MUST be resolved independently for each request and MUST NOT retain the tenant from an earlier request. Access without a resolved tenant MUST fail closed.

#### Scenario: Successive tenants
- **WHEN** requests for tenant A and tenant B execute successively in the same application process
- **THEN** each response contains only resources belonging to its supplied tenant

#### Scenario: Missing header after valid request
- **WHEN** a request without X-Tenant-ID follows a valid tenant A request
- **THEN** it returns HTTP 400 instead of reusing tenant A's context
