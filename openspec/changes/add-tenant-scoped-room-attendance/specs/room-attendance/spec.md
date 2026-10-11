# Spec Delta

## Purpose

Give nursery staff a reliable room attendance lifecycle and let managers retrieve the children currently present without including completed visits.

## ADDED Requirements

### Requirement: Record room check-in
POST /rooms/{room}/check-ins with a positive integer child_id SHALL create active attendance for an existing child and room in the request tenant, returning JSON HTTP 201. The response MUST contain data with id, room_id, child_id, checked_in_at, and checked_out_at set to null. Times SHALL be server-generated ISO 8601 UTC values. Tenant isolation rules apply to every lookup.

#### Scenario: Successful check-in
- **WHEN** an existing tenant child without active attendance is checked into an existing tenant room
- **THEN** HTTP 201 returns a new check-in ID and check-in time, and the child becomes a current occupant

#### Scenario: Invalid payload
- **WHEN** child_id is missing, null, non-integer, zero, or negative
- **THEN** JSON HTTP 422 contains message and errors.child_id, and creates no attendance

#### Scenario: Unknown room or child
- **WHEN** a valid check-in request references a room or child not found in the request tenant
- **THEN** JSON HTTP 404 is returned and no attendance is created

### Requirement: One active room per child
A child MUST have at most one active check-in across the tenant's rooms and nurseries. A new check-in while one is active SHALL return JSON HTTP 409 with a message, without moving the child or changing attendance. The invariant MUST hold for overlapping requests. A completed visit SHALL NOT prevent a later check-in.

#### Scenario: Duplicate in same room
- **WHEN** staff check an already present child into the same room again
- **THEN** HTTP 409 is returned and only the original check-in remains active

#### Scenario: Second room
- **WHEN** staff check an already present child into another room, including a room in another nursery of the same tenant
- **THEN** HTTP 409 is returned and the child's original room remains occupied

#### Scenario: Overlapping requests
- **WHEN** two valid check-in requests for the same child overlap
- **THEN** no more than one succeeds with HTTP 201 and the other receives HTTP 409 without creating a second active check-in

#### Scenario: Re-entry
- **WHEN** staff check a child in after the previous visit was checked out
- **THEN** a new check-in succeeds and the previous visit remains completed

### Requirement: Check out attendance
POST /check-ins/{id}/check-out SHALL end an active tenant check-in with a server-generated ISO 8601 UTC checked_out_at and return JSON HTTP 200 using the check-in data shape. Repeated check-out SHALL return HTTP 200 with the original checkout time, without creating or modifying other visits. Unknown check-ins SHALL return JSON HTTP 404.

#### Scenario: Active check-out
- **WHEN** staff check out an active tenant check-in
- **THEN** HTTP 200 returns a checkout time and that child disappears from the room's current occupants

#### Scenario: Repeated check-out
- **WHEN** staff check out a completed check-in again
- **THEN** HTTP 200 returns the unchanged original checkout time

#### Scenario: Old visit after re-entry
- **WHEN** staff repeat checkout of an old completed visit while the child has a new active visit
- **THEN** the old visit remains unchanged and the new visit remains active

#### Scenario: Unknown check-in
- **WHEN** the requested check-in does not exist in the request tenant
- **THEN** HTTP 404 is returned and existing attendance is unchanged

### Requirement: List current occupants
GET /rooms/{room}/occupants SHALL return JSON HTTP 200 with a data array containing only active occupants of that tenant room. Each item MUST contain child_id, child_name, check_in_id, and checked_in_at. Items SHALL be ordered by child_id ascending. Empty rooms SHALL return data as an empty array; unknown rooms SHALL return JSON HTTP 404.

#### Scenario: Active and completed visits
- **WHEN** the requested room has active visits and completed visits while another room also has active visits
- **THEN** only active children of the requested room are returned with usable check-in IDs

#### Scenario: Empty room
- **WHEN** an existing room has no active visits
- **THEN** HTTP 200 returns { "data": [] }

### Requirement: Reproducible submission
The repository MUST provide setup, seed, serve, and test commands in its README, plus endpoint examples. It MUST explain the active-room conflict decision, assumptions, intentional omissions, future improvements, and actual AI use. Automated tests MUST cover the attendance lifecycle, failure cases, and tenant isolation.

#### Scenario: Reviewer runs submission
- **WHEN** a reviewer follows the README from a fresh checkout with the documented prerequisites
- **THEN** they can initialize demo data, call the three endpoints, run the tests, and understand the decisions without frontend tooling
