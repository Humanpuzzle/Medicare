# 07 — Testing Specification

**Document:** `07-TESTING-SPEC.md`
**Version:** `1.0`
**Status:** Approved for implementation
**Test framework:** PHPUnit
**Primary test type:** Feature tests

---

# 1. Purpose

This document defines the automated testing strategy for the medical appointment booking API.

The purpose is to ensure that:

* API endpoints behave according to the specification,
* validation rules are enforced,
* business rules are enforced,
* appointment conflicts cannot occur,
* availability rules are respected,
* appointment state transitions are valid,
* slot generation is correct,
* soft-deleted records do not incorrectly participate in active operations,
* API error responses are predictable.

The tests are also intended to act as an executable specification for AI-assisted development.

A developer or coding agent should be able to determine the expected behavior of the application from:

```text
PROJECT-SPEC
DATABASE-SPEC
ARCHITECTURE
API-SPEC
TESTING-SPEC
```

---

# 2. Testing Philosophy

The project should prefer tests that verify observable application behavior.

Primary flow:

```text
HTTP Request
    ↓
Form Request / Validation
    ↓
Application / Service Logic
    ↓
Database
    ↓
API Resource
    ↓
HTTP Response
```

The most important tests therefore exercise the application through its HTTP API.

Unit tests should be added where a piece of logic is sufficiently isolated and complex to justify them.

---

# 3. Test Types

The project uses three complementary levels.

## 3.1 Feature tests

Feature tests are the primary test type.

They verify complete API behavior.

Examples:

```text
POST /api/v1/appointments
GET /api/v1/doctors
GET /api/v1/doctors/{doctor}/slots
PATCH /api/v1/appointments/{appointment}
```

Feature tests should verify:

* HTTP status,
* response structure,
* validation,
* database state,
* business rules.

---

## 3.2 Unit tests

Unit tests may be used for isolated domain logic.

Good candidates include:

* slot generation,
* appointment state transition logic,
* interval overlap logic,
* date/time calculations.

Do not create unit tests merely to increase the test count.

---

## 3.3 Integration-style database behavior

Laravel Feature tests may use the database directly to verify:

* relationships,
* soft deletes,
* foreign keys,
* unique constraints,
* persisted status changes.

---

# 4. Test Environment

Tests must use a separate test database configuration.

The preferred configuration is SQLite.

Tests must never depend on the developer's local development database.

Each test should start from a predictable state.

Recommended Laravel traits:

```php
use RefreshDatabase;
```

The exact database-reset strategy may be adjusted according to the final Laravel version and test performance.

---

# 5. Test Directory Structure

Recommended structure:

```text
tests/
├── Feature/
│   ├── Api/
│   │   ├── DoctorTest.php
│   │   ├── PatientTest.php
│   │   ├── AvailabilityTest.php
│   │   ├── AppointmentTest.php
│   │   └── SlotTest.php
│   │
│   └── ErrorHandlingTest.php
│
└── Unit/
    ├── Services/
    │   ├── AvailabilityServiceTest.php
    │   ├── AppointmentServiceTest.php
    │   └── SlotServiceTest.php
    │
    └── Rules/
        └── AppointmentStatusTest.php
```

The exact structure may be simplified if the final implementation contains fewer classes.

---

# 6. Test Naming

Test names must describe behavior.

Preferred:

```php
it_creates_an_appointment_inside_available_slot()
```

or:

```php
public function test_it_creates_an_appointment_inside_an_available_slot(): void
```

Avoid:

```php
testAppointment()
testWorks()
testApi()
```

A test name should make the expected behavior immediately understandable.

---

# 7. API Response Assertions

Tests must verify both:

1. HTTP status;
2. relevant response body.

For successful JSON responses:

```php
$response
    ->assertOk()
    ->assertJsonStructure([
        'data',
    ]);
```

For resource collections:

```php
$response
    ->assertOk()
    ->assertJsonStructure([
        'data' => [
            '*' => [
                'id',
            ],
        ],
    ]);
```

Tests should not assert the complete JSON response unnecessarily if only part of the response is contractually relevant.

---

# 8. HTTP Status Code Contract

The following status codes must be tested.

| Situation               |                          Status |
| ----------------------- | ------------------------------: |
| Successful GET          |                           `200` |
| Successful creation     |                           `201` |
| Successful update       |                           `200` |
| Successful deletion     | `204` or documented alternative |
| Validation error        |                           `422` |
| Resource not found      |                           `404` |
| Business conflict       |                           `409` |
| Unexpected server error |                           `500` |

The final API specification is authoritative if an endpoint has a specifically documented alternative.

---

# 9. Doctor Tests

## DOC-001 — List doctors

Request:

```http
GET /api/v1/doctors
```

Expected:

```text
200 OK
```

Verify:

* `data` exists,
* doctors are returned,
* response uses the expected Resource structure,
* default ordering is deterministic,
* pagination metadata exists if pagination is enabled.

---

## DOC-002 — Show doctor

```http
GET /api/v1/doctors/{doctor}
```

Expected:

```text
200 OK
```

Verify the requested doctor is returned.

---

## DOC-003 — Missing doctor

```http
GET /api/v1/doctors/999999
```

Expected:

```text
404 Not Found
```

---

## DOC-004 — Create doctor

Valid payload must produce:

```text
201 Created
```

Verify:

* database record exists,
* returned resource contains the expected fields.

---

## DOC-005 — Doctor validation

Invalid input must return:

```text
422 Unprocessable Entity
```

Test at minimum:

* missing name,
* invalid email,
* missing email,
* missing specialty.

---

## DOC-006 — Unique doctor email

Attempt to create a doctor using an existing email.

Expected:

```text
422 Unprocessable Entity
```

---

## DOC-007 — Patch doctor

Use:

```http
PATCH /api/v1/doctors/{doctor}
```

Only the supplied fields should be changed.

Unspecified fields must remain unchanged.

---

## DOC-008 — Soft delete doctor

Delete a doctor.

Verify:

* doctor receives `deleted_at`,
* doctor is not returned by normal queries,
* related active business records behave according to the database specification.

---

# 10. Patient Tests

## PAT-001 — List patients

```http
GET /api/v1/patients
```

Expected:

```text
200 OK
```

---

## PAT-002 — Show patient

```http
GET /api/v1/patients/{patient}
```

Expected:

```text
200 OK
```

---

## PAT-003 — Missing patient

Expected:

```text
404 Not Found
```

---

## PAT-004 — Create patient

Valid input must produce:

```text
201 Created
```

---

## PAT-005 — Patient validation

Verify:

* required name,
* valid email,
* required phone.

Invalid input:

```text
422
```

---

## PAT-006 — Unique patient email

Duplicate email:

```text
422
```

---

## PAT-007 — Patch patient

Verify partial update behavior.

---

## PAT-008 — Soft delete patient

Verify:

* `deleted_at` is populated,
* normal queries exclude the patient.

---

# 11. Availability Tests

Availability is one of the primary business-rule areas.

---

## AV-001 — Create future availability

Given:

```text
Doctor exists
Future start
Future end
Duration >= 30 minutes
```

Creating the availability must succeed.

Expected:

```text
201 Created
```

---

## AV-002 — Doctor must exist

Attempt to create availability for a nonexistent doctor.

Expected:

```text
422 Unprocessable Entity
```

or the exact status defined by the API specification.

---

## AV-003 — Start must precede end

Example:

```text
starts_at = 11:00
ends_at   = 10:00
```

Expected:

```text
422
```

---

## AV-004 — Minimum duration

Example:

```text
10:00 - 10:15
```

must be rejected.

Example:

```text
10:00 - 10:30
```

must be accepted.

---

## AV-005 — Past availability

An availability entirely in the past must be rejected.

Expected:

```text
422
```

---

## AV-006 — Overlapping availability

Given:

```text
Existing:
10:00 - 11:00
```

Attempt:

```text
10:30 - 11:30
```

Expected:

```text
409 Conflict
```

---

## AV-007 — Availability starts inside existing period

Existing:

```text
10:00 - 12:00
```

New:

```text
11:00 - 13:00
```

Must be rejected.

---

## AV-008 — Availability contains existing period

Existing:

```text
11:00 - 12:00
```

New:

```text
10:00 - 13:00
```

Must be rejected.

---

## AV-009 — Exact duplicate

Existing:

```text
10:00 - 11:00
```

New:

```text
10:00 - 11:00
```

Must be rejected.

---

## AV-010 — Adjacent availability

Existing:

```text
10:00 - 11:00
```

New:

```text
11:00 - 12:00
```

Must be allowed.

This confirms that touching boundaries are not considered overlapping.

---

## AV-011 — Update availability

A valid future update must succeed.

---

## AV-012 — Update causing overlap

An update that causes an overlap with another availability must be rejected.

The record must remain unchanged.

---

## AV-013 — Delete availability

After soft deletion:

* it must not appear in normal availability queries,
* it must not produce future appointment slots.

---

# 12. Appointment Tests

Appointment creation is the most important business-flow area.

---

## AP-001 — Create appointment inside available slot

Given:

```text
Availability:
09:00 - 12:00

Slot duration:
30 minutes
```

Request:

```text
09:00 - 09:30
```

must succeed.

Expected:

```text
201 Created
```

Initial status:

```text
pending
```

---

## AP-002 — Appointment outside availability

Availability:

```text
09:00 - 12:00
```

Appointment:

```text
12:00 - 12:30
```

Must be rejected.

---

## AP-003 — Appointment partially outside availability

Availability:

```text
09:00 - 12:00
```

Appointment:

```text
11:45 - 12:15
```

Must be rejected.

---

## AP-004 — Past appointment

An appointment whose start time is in the past must be rejected.

Expected:

```text
422
```

---

## AP-005 — Invalid time ordering

Example:

```text
start = 11:00
end   = 10:30
```

Must be rejected.

---

## AP-006 — Invalid 15-minute boundary

Appointment times must follow the 15-minute grid.

Valid:

```text
09:00
09:15
09:30
09:45
10:00
```

Invalid:

```text
09:10
09:20
09:35
09:50
```

Invalid requests must be rejected.

---

## AP-007 — Appointment duration

The appointment duration must conform to the selected/generated slot duration.

The test must verify that arbitrary durations cannot bypass the slot model.

---

## AP-008 — Doctor conflict

Existing appointment:

```text
10:00 - 10:30
```

Same doctor, new appointment:

```text
10:15 - 10:45
```

Expected:

```text
409 Conflict
```

---

## AP-009 — Doctor adjacent appointment

Existing:

```text
10:00 - 10:30
```

New:

```text
10:30 - 11:00
```

must be allowed when both slots are otherwise valid.

---

## AP-010 — Patient conflict

Existing:

```text
Patient A
Doctor 1
10:00 - 10:30
```

New:

```text
Patient A
Doctor 2
10:15 - 10:45
```

must be rejected.

Expected:

```text
409 Conflict
```

---

## AP-011 — Different patient and doctor

Two appointments with different patients and doctors may coexist when their respective availability permits them.

---

## AP-012 — Appointment starts exactly at availability boundary

If availability is:

```text
09:00 - 10:00
```

and slot duration is:

```text
30 minutes
```

then:

```text
09:00 - 09:30
09:30 - 10:00
```

are valid.

---

## AP-013 — Appointment cannot exceed availability end

```text
09:30 - 10:30
```

must not be accepted for:

```text
09:00 - 10:00
```

---

# 13. Appointment Status Tests

---

## ST-001 — New appointment is pending

Every new appointment must have:

```text
pending
```

---

## ST-002 — Pending → Confirmed

Allowed.

Verify database state:

```text
confirmed
```

---

## ST-003 — Pending → Cancelled

Allowed.

---

## ST-004 — Confirmed → Completed

Allowed.

---

## ST-005 — Confirmed → Cancelled

Allowed only when the appointment starts at least 24 hours in the future.

---

## ST-006 — Confirmed cancellation under 24 hours

If the appointment starts in less than 24 hours:

```text
confirmed → cancelled
```

must be rejected.

Expected:

```text
409 Conflict
```

---

## ST-007 — Exactly 24 hours

The exact boundary must be explicitly tested.

Given:

```text
appointment starts exactly 24 hours from the reference time
```

the implementation must behave according to the finalized business rule.

The test must freeze time to make this deterministic.

---

## ST-008 — Completed is terminal

The following must fail:

```text
completed → pending
completed → confirmed
completed → cancelled
```

---

## ST-009 — Cancelled is terminal

The following must fail:

```text
cancelled → pending
cancelled → confirmed
cancelled → completed
```

---

# 14. Cancellation Tests

## CAN-001 — Cancellation reason

If the final API decision requires a cancellation reason, cancelling without one must fail validation.

If the field is optional, the test must verify that cancellation without a reason remains valid.

The API specification is authoritative.

---

## CAN-002 — Cancellation reason persisted

When provided:

```text
cancellation_reason
```

must be persisted.

---

## CAN-003 — Cancellation does not create an active conflict

After an appointment is cancelled, its time should become available again where all other business rules permit it.

---

# 15. Slot Generation Tests

Slot generation is tested independently from appointment creation where practical.

---

## SLOT-001 — Basic generation

Given:

```text
09:00 - 10:00
30-minute duration
```

return:

```text
09:00 - 09:30
09:30 - 10:00
```

---

## SLOT-002 — 15-minute duration grid

Where the configured slot duration permits it, generated starts must remain aligned to:

```text
00
15
30
45
```

minutes.

---

## SLOT-003 — No partial slot

Availability:

```text
09:00 - 10:20
```

Duration:

```text
30 minutes
```

Only complete slots may be generated.

Do not generate:

```text
10:00 - 10:30
```

because it exceeds availability.

---

## SLOT-004 — Adjacent slots

Generated slots must not overlap.

---

## SLOT-005 — Blocking pending appointment

A `pending` appointment blocks its corresponding time slot.

---

## SLOT-006 — Blocking confirmed appointment

A `confirmed` appointment blocks its corresponding time slot.

---

## SLOT-007 — Completed appointment

A `completed` appointment does not block future slot generation.

---

## SLOT-008 — Cancelled appointment

A `cancelled` appointment does not block the slot.

---

## SLOT-009 — Soft-deleted appointment

A soft-deleted appointment must not block an active slot.

---

## SLOT-010 — Soft-deleted availability

A soft-deleted availability must not generate slots.

---

## SLOT-011 — Past slots

Past slots must not be returned as bookable future slots.

---

# 16. Doctor Appointment Listing

The API must support listing appointments belonging to a specific doctor.

Example:

```http
GET /api/v1/doctors/{doctor}/appointments
```

Tests must verify:

* only the requested doctor's appointments are returned,
* other doctors' appointments are excluded,
* pagination works,
* fixed ordering is preserved,
* status filtering works where supported.

---

# 17. Patient Appointment Listing

If the API exposes patient-specific appointment listing, tests must verify:

* only the requested patient's appointments are returned,
* other patients are excluded,
* pagination works,
* filtering works,
* fixed ordering is preserved.

---

# 18. Pagination Tests

Every paginated endpoint must have tests for:

## PAG-001 — Default pagination

Verify:

* response contains `data`,
* pagination metadata exists,
* default page size is applied.

---

## PAG-002 — Explicit page

Example:

```http
?page=2
```

must return the second page.

---

## PAG-003 — Page size

If the API supports:

```http
?per_page=10
```

verify the configured maximum is respected.

---

## PAG-004 — Invalid pagination

Invalid values must return the documented validation response.

---

# 19. Fixed Ordering Tests

The API must have deterministic backend ordering.

Tests must verify that records are not returned in arbitrary database order.

For example:

```text
ORDER BY created_at DESC, id DESC
```

or the exact ordering defined by the API specification.

The test must use records with deliberately controlled timestamps/IDs so that the expected order is unambiguous.

---

# 20. Soft Delete Tests

Soft deletion must be tested explicitly.

For each soft-deletable model:

1. create record,
2. delete record,
3. verify `deleted_at`,
4. verify normal query excludes record,
5. verify active business logic ignores record.

At minimum verify:

```text
Doctor
Availability
Appointment
```

and any other model that receives `SoftDeletes`.

---

# 21. Validation Tests

Validation tests must cover:

* missing required fields,
* invalid data types,
* invalid email,
* duplicate email,
* nonexistent foreign keys,
* invalid datetime,
* invalid datetime order,
* invalid 15-minute boundary,
* invalid duration,
* invalid enum/status,
* invalid query parameters.

Every validation failure must produce the documented HTTP status and error structure.

---

# 22. Not Found Tests

Every resource endpoint that accepts an ID must have a missing-resource test.

Examples:

```text
GET doctor
GET patient
GET availability
GET appointment
PATCH doctor
PATCH patient
PATCH availability
PATCH appointment
DELETE doctor
DELETE patient
DELETE availability
DELETE appointment
```

Expected:

```text
404 Not Found
```

where applicable.

---

# 23. Business Conflict Tests

Business conflicts must use the documented conflict response.

Minimum cases:

```text
overlapping availability
doctor appointment conflict
patient appointment conflict
invalid appointment state transition
confirmed appointment cancellation < 24h
appointment outside availability
```

Expected:

```text
409 Conflict
```

where the API specification defines the conflict as a business-state conflict.

---

# 24. Transaction Tests

Transactional operations should be tested from the resulting state.

For appointment creation:

If any business condition fails:

```text
appointment must not be partially persisted
```

For example:

```text
conflict detected
    ↓
request fails
    ↓
no new appointment exists
```

The test should verify database state rather than implementation details of the transaction itself.

---

# 25. Concurrency Considerations

The test suite should cover logical conflict prevention.

The implementation must ensure that two conflicting appointment requests cannot both successfully create conflicting appointments.

If the final implementation uses database locking or another concurrency mechanism, integration tests should verify the resulting invariant.

The test should focus on:

```text
at most one conflicting active appointment
```

rather than on a specific locking implementation.

---

# 26. Database Integrity Tests

Verify:

* foreign keys,
* unique constraints,
* nullable fields,
* enum casting,
* timestamps,
* soft deletes.

Database constraints should complement, not replace, application validation.

---

# 27. Resource Tests

API Resources should be indirectly tested through Feature tests.

Verify that responses:

* contain the documented fields,
* do not expose unintended internal fields,
* use the documented datetime representation,
* represent enum values correctly.

Do not test Laravel Resource implementation details.

Test the public API contract instead.

---

# 28. Time-Dependent Tests

All tests involving:

```text
now()
today()
future
24 hours
past
```

must use a controlled clock.

Use Laravel's time-freezing facilities where appropriate.

Example concept:

```php
Carbon::setTestNow(...);
```

The exact API depends on the Laravel version used.

Tests must never depend on the actual wall clock.

---

# 29. Test Data Strategy

Use factories for ordinary test data.

Use explicit values for boundary cases.

Example:

```text
Factory → ordinary doctor
Explicit datetime → exact 24-hour cancellation boundary
Explicit interval → overlap test
Explicit status → state transition test
```

Avoid tests that depend on randomly generated values when the exact value matters.

---

# 30. Test Independence

Every test must be independently executable.

A test must not rely on:

* another test having run,
* database state left by another test,
* seed data unless explicitly testing the seeder,
* execution order.

Tests should be safe to run:

```bash
php artisan test
```

or individually:

```bash
php artisan test --filter=Appointment
```

---

# 31. Test Coverage Priorities

Testing effort should follow business risk.

Priority 1:

```text
Appointment creation
Appointment conflicts
Availability overlap
Slot generation
Status transitions
Cancellation rules
```

Priority 2:

```text
CRUD validation
Soft deletes
Pagination
Filtering
```

Priority 3:

```text
Simple resource serialization
Basic CRUD success cases
```

The project should not optimize for a high coverage percentage at the expense of meaningful business-rule coverage.

---

# 32. Minimum Test Matrix

The implementation should contain substantially more than the assignment's minimum five tests.

At minimum, the following behavior groups must be covered:

| Area         | Minimum coverage                       |
| ------------ | -------------------------------------- |
| Doctor       | CRUD + validation                      |
| Patient      | CRUD + validation                      |
| Availability | creation + overlap + future + duration |
| Appointment  | creation + availability + conflicts    |
| Status       | valid + invalid transitions            |
| Cancellation | 24-hour rule                           |
| Slots        | generation + occupied slots            |
| Pagination   | list endpoint                          |
| Ordering     | deterministic ordering                 |
| Soft delete  | active behavior                        |
| Errors       | 404 / 409 / 422                        |

---

# 33. Quality Gate

Before a milestone is considered complete:

```bash
php artisan test
```

must pass.

Then:

```bash
vendor/bin/phpstan analyse
```

must pass.

Then:

```bash
vendor/bin/pint --test
```

must pass.

Recommended complete verification:

```bash
php artisan test && \
vendor/bin/phpstan analyse && \
vendor/bin/pint --test
```

On Windows, commands may be executed individually if shell syntax differs.

---

# 34. CI / Final Verification

If CI is configured, it should execute at least:

```text
composer install
php artisan test
vendor/bin/phpstan analyse
vendor/bin/pint --test
```

CI configuration is optional for the assignment unless explicitly required.

The absence of CI must not reduce the local quality gate.

---

# 35. Definition of Done

Testing is complete when:

* [ ] all critical business rules have automated tests,
* [ ] appointment creation is covered,
* [ ] availability overlap is covered,
* [ ] doctor conflicts are covered,
* [ ] patient conflicts are covered,
* [ ] slot generation is covered,
* [ ] 15-minute boundaries are covered,
* [ ] status transitions are covered,
* [ ] 24-hour cancellation rule is covered,
* [ ] soft-delete behavior is covered,
* [ ] pagination is covered,
* [ ] fixed ordering is covered,
* [ ] validation errors are covered,
* [ ] not-found errors are covered,
* [ ] business conflicts are covered,
* [ ] time-dependent tests use controlled time,
* [ ] tests are independent,
* [ ] PHPUnit passes,
* [ ] PHPStan passes,
* [ ] Pint passes.

---

# 36. AI Coding Rule

When implementing a feature, the coding agent should follow this sequence:

```text
1. Read the relevant specification.
2. Identify the business rules.
3. Identify the affected models/services/controllers.
4. Write or update the relevant tests.
5. Implement the smallest change required.
6. Run the focused tests.
7. Run the complete test suite.
8. Run PHPStan.
9. Run Pint.
10. Only then proceed to the next feature.
```

The agent must not consider a feature complete solely because the endpoint returns the expected response for the happy path.

For business-critical functionality, both:

```text
happy path
```

and:

```text
invalid / conflicting / boundary cases
```

must be implemented and tested.

---

# 37. Final Testing Principle

The test suite should protect the system's invariants.

The most important invariants are:

```text
No overlapping doctor availability.
No appointment outside availability.
No conflicting doctor appointments.
No conflicting patient appointments.
No invalid appointment state transitions.
No invalid cancellation under the defined time rule.
No invalid slot boundaries.
No active use of soft-deleted records.
```

These invariants are more important than achieving a particular raw test-count or coverage percentage.

The test suite is considered successful when it makes these rules difficult to accidentally break during subsequent implementation or refactoring.
