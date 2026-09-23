# Medicare Backend Test — AI Implementation Plan

## 1. Purpose

This document defines the implementation workflow for the Medicare Backend Test.

The project must be implemented milestone-by-milestone.

The AI implementation agent must:

* read the project specification before implementation,
* implement only the requested milestone,
* respect all finalized project decisions,
* avoid speculative features,
* verify each milestone before moving to the next one,
* keep commits focused,
* maintain a clean working tree.

The following documents are the authoritative project documentation:

```text
docs/
├── PROJECT-SPEC.md
├── 02-DATABASE-SPEC.md
├── 03-ARCHITECTURE.md
├── 04-API-SPEC.md
├── 05-IMPLEMENTATION-SPEC.md
└── 06-SETUP-AND-WORKFLOW.md
```

## 2. Source of Truth

Before implementing any milestone, the agent must read the relevant project specification files.

If documents appear to contain conflicting information, the finalized decisions in `PROJECT-SPEC.md` take precedence.

Important finalized decisions:

```text
API prefix:
    /api/v1

Internal application timezone:
    UTC

Pagination:
    default = 25
    maximum = 100

Email:
    case-insensitive
    normalized to lowercase before persistence

Appointment:
    minimum duration = 30 minutes
    start time = 15-minute grid
    duration = integer multiple of availability.slot_duration
    fully contained within one availability
    conflict interval = [start_time, end_time)

Cancellation:
    cancellation_reason is nullable at database level
    required when status becomes cancelled
    confirmed → cancelled allowed when >= 24 hours remain

Database:
    SQLite

Deletion:
    soft deletes where defined by the database specification
```

The agent must not silently change these decisions.

---

# 3. Global Agent Rules

## 3.1 Milestone isolation

Only implement the current milestone.

Do not prematurely implement functionality belonging to later milestones.

If a later milestone requires a placeholder or minimal supporting structure, implement only what is strictly necessary.

---

## 3.2 Specification compliance

The agent must not invent requirements.

When the specification already defines behavior, follow it instead of introducing a generic Laravel alternative.

---

## 3.3 Specification conflicts

If a genuine contradiction exists between the specifications:

1. identify the exact conflicting sections,
2. identify the affected implementation,
3. do not silently choose a new behavior,
4. do not modify the specification,
5. report the conflict.

Do not stop merely because an implementation detail is unspecified. Use normal Laravel conventions where the specification leaves implementation freedom.

---

## 3.4 Architecture

Follow:

```text
HTTP Request
    ↓
FormRequest
    ↓
Controller
    ↓
Service
    ↓
Model / Database
    ↓
API Resource
    ↓
JSON Response
```

Controllers must remain thin.

Business rules must not be implemented directly inside controllers.

---

## 3.5 Avoid unnecessary architecture

Do not introduce:

* Repository pattern
* Unit of Work abstraction
* CQRS
* Event Sourcing
* full DDD framework
* unnecessary domain layers
* unnecessary interfaces
* unnecessary factories for trivial behavior
* microservices
* event bus
* queues
* authentication
* authorization
* frontend
* Laravel Boost

Use Laravel mechanisms directly where appropriate.

---

## 3.6 Code quality

Custom PHP code must use:

```php
declare(strict_types=1);
```

Use:

* typed properties,
* parameter types,
* return types,
* constructor property promotion where appropriate,
* enums for fixed domain values,
* final classes where inheritance is not intended,
* Laravel conventions,
* PSR-12,
* Laravel Pint.

---

## 3.7 Testing

Feature tests are the primary test layer.

Every milestone introducing behavior must include or update tests for that behavior.

Tests must cover both:

* successful behavior,
* relevant failure/boundary behavior.

---

## 3.8 Git

Each milestone should finish with:

```text
working tree clean
tests passing
static analysis passing
formatting passing
focused commit created
```

Commit messages should follow Conventional Commits.

Examples:

```text
chore: initialize Laravel project
feat: add domain database schema
feat: implement availability domain
feat: implement appointment domain
feat: implement API endpoints
test: expand API feature coverage
chore: finalize quality checks
docs: finalize project documentation
```

Do not mix unrelated changes into milestone commits.

---

# 4. Milestone Overview

```text
M1  Laravel Foundation
        ↓
M2  Database & Models
        ↓
M3  Availability & Slot Domain
        ↓
M4  Appointment Domain
        ↓
M5  REST API Layer
        ↓
M6  Feature Test Completion
        ↓
M7  Quality Gate & Integration
        ↓
M8  Final Review & Delivery
```

---

# MILESTONE 1 — Laravel Foundation

## Objective

Create the clean Laravel project foundation and development tooling.

## Scope

Implement:

* Laravel application initialization,
* PHP 8.3+ compatibility,
* Composer setup,
* SQLite configuration,
* `.env` configuration,
* application timezone configuration for UTC,
* `/api/v1` API route foundation,
* PHPUnit,
* Laravel Pint,
* PHPStan,
* Larastan,
* `.gitignore`,
* basic README,
* Git repository.

Do not implement domain functionality yet.

---

## Expected Structure

At minimum:

```text
app/
bootstrap/
config/
database/
public/
resources/
routes/
storage/
tests/
composer.json
phpunit.xml
pint.json
phpstan.neon
README.md
.gitignore
```

The exact structure may follow the Laravel version used by the project.

---

## Acceptance Criteria

### Application

* Laravel application boots successfully.
* PHP version requirement is satisfied.
* Composer dependencies install successfully.
* `.env` is not committed.
* `vendor/` is not committed.

### Database

* SQLite is configured.
* Database connection works.
* Laravel migrations can execute.

### API

The API namespace is:

```text
/api/v1
```

A minimal API route foundation exists.

No actual domain endpoint implementation is required yet.

### Testing

PHPUnit runs successfully.

### Static analysis

PHPStan + Larastan can execute successfully against application code.

### Formatting

Laravel Pint can execute successfully.

### Git

Initial repository is initialized and first focused commit is created.

---

## Must Not

Do not implement:

* Doctor model,
* Patient model,
* Availability model,
* Appointment model,
* domain services,
* API Resources,
* business rules,
* appointment logic,
* authentication.

---

## Verification Checklist

```text
[ ] php -v
[ ] composer install
[ ] php artisan about
[ ] SQLite connection works
[ ] php artisan migrate
[ ] php artisan test
[ ] vendor/bin/pint --test
[ ] vendor/bin/phpstan analyse
[ ] /api/v1 route foundation works
[ ] git status is clean
```

---

# MILESTONE 2 — Database & Models

## Objective

Implement the complete persistence layer according to `02-DATABASE-SPEC.md`.

## Scope

Implement:

* database migrations,
* foreign keys,
* indexes,
* unique constraints,
* case-insensitive email uniqueness strategy,
* soft deletes where specified,
* timestamps,
* models,
* model relationships,
* casts,
* `AppointmentStatus` enum,
* factories,
* seeders where required.

Entities:

```text
Doctor
Patient
Availability
Appointment
```

---

## Doctor

Implement:

```text
id
name
email
specialty
created_at
updated_at
deleted_at
```

where soft deletion is defined by the database specification.

Relationships:

```text
Doctor hasMany Availability
Doctor hasMany Appointment
```

---

## Patient

Implement:

```text
id
name
email
phone
created_at
updated_at
deleted_at
```

where applicable.

Relationship:

```text
Patient hasMany Appointment
```

---

## Availability

Implement:

```text
id
doctor_id
starts_at
ends_at
slot_duration
created_at
updated_at
deleted_at
```

where applicable.

Relationship:

```text
Availability belongsTo Doctor
```

---

## Appointment

Implement:

```text
id
patient_id
doctor_id
start_time
end_time
status
cancellation_reason
created_at
updated_at
deleted_at
```

Relationships:

```text
Appointment belongsTo Patient
Appointment belongsTo Doctor
```

---

## AppointmentStatus

Enum values:

```text
pending
confirmed
completed
cancelled
```

The enum must represent only the allowed states.

---

## Email Uniqueness

Doctor and patient email addresses must be normalized to lowercase before persistence.

The database must additionally enforce uniqueness.

Application validation alone is insufficient.

The implementation must prevent race-condition duplicates as far as the chosen SQLite strategy allows.

---

## Date/Time Persistence

Internal date/time handling uses UTC.

Models must cast date/time attributes appropriately.

Do not store application business timestamps in local Budapest time.

---

## Acceptance Criteria

```text
[ ] all required migrations exist
[ ] foreign keys are defined
[ ] required indexes exist
[ ] email uniqueness is database-enforced
[ ] emails are normalized to lowercase
[ ] soft deletes are implemented as specified
[ ] all relationships work
[ ] AppointmentStatus enum exists
[ ] datetime casts are correct
[ ] migrations run from an empty SQLite database
[ ] rollback works
[ ] factories can create valid records
[ ] seeders can create a usable development dataset
```

---

## Must Not

Do not implement:

* controllers,
* API Resources,
* HTTP validation,
* appointment business services,
* slot generation services,
* authentication,
* unrelated abstractions.

---

## Verification Checklist

```text
[ ] php artisan migrate:fresh
[ ] php artisan db:seed
[ ] model factories work
[ ] relationships verified
[ ] email uniqueness verified
[ ] lowercase normalization verified
[ ] soft deletes verified
[ ] php artisan test
[ ] vendor/bin/pint --test
[ ] vendor/bin/phpstan analyse
```

Commit:

```text
feat: add domain database schema
```

---

# MILESTONE 3 — Availability & Slot Domain

## Objective

Implement availability business rules and slot generation.

## Scope

Implement:

```text
AvailabilityService
SlotService
```

and supporting rules/classes where justified.

---

## Availability Rules

An availability:

* must reference an existing doctor,
* must start before it ends,
* must be in the future,
* must be at least 30 minutes long,
* must not overlap another availability for the same doctor,
* may be adjacent to another availability.

Valid:

```text
09:00–10:00
10:00–11:00
```

Invalid:

```text
09:00–10:00
09:30–11:00
```

---

## Slot Generation

Slots are generated from:

```text
availability.starts_at
availability.ends_at
availability.slot_duration
```

Example:

```text
09:00–11:00
slot_duration = 30
```

produces:

```text
09:00–09:30
09:30–10:00
10:00–10:30
10:30–11:00
```

Slot generation must not create slots extending beyond the availability.

---

## Booking Start Semantics

A slot represents a bookable appointment start time.

Appointment duration is not globally fixed to the slot duration.

The finalized project rules require appointment starts to use the 15-minute grid.

The implementation must therefore ensure that exposed/generated booking start times are valid according to the finalized project specification.

---

## Available Slots

The slot service must account for existing appointments.

A slot must not be reported as available when an existing appointment makes the generated booking interval unavailable according to the API/domain specification.

The exact response representation belongs to the API layer.

---

## Filtering Requirements

The domain implementation must support the API requirements for:

* doctor filtering,
* date filtering,
* date/range filtering where specified,
* pagination support at API level.

Do not move HTTP concerns into the domain service.

---

## Acceptance Criteria

```text
[ ] valid availability can be created
[ ] past availability is rejected
[ ] invalid start/end order is rejected
[ ] availability shorter than 30 minutes is rejected
[ ] overlapping availability is rejected
[ ] adjacent availability is accepted
[ ] slots are generated correctly
[ ] slots never exceed availability end
[ ] existing appointments affect availability
[ ] UTC is consistently used
```

---

## Must Not

Do not implement:

* authentication,
* authorization,
* frontend,
* repositories,
* appointment state transitions,
* cancellation workflow,
* unrelated APIs.

---

## Verification Checklist

Tests must cover:

```text
[ ] valid availability
[ ] past availability
[ ] invalid interval
[ ] <30 minute availability
[ ] overlapping availability
[ ] adjacent availability
[ ] slot generation
[ ] appointment-aware slot availability
[ ] UTC boundary behavior
```

Run:

```text
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
```

Commit:

```text
feat: implement availability and slot domain
```

---

# MILESTONE 4 — Appointment Domain

## Objective

Implement all appointment business rules.

This is the most important domain milestone.

## Scope

Implement:

```text
AppointmentService
```

and supporting domain rules.

---

## Appointment Creation

An appointment requires:

```text
patient exists
doctor exists
start_time is in the future
appointment is inside one availability
duration >= 30 minutes
duration is a multiple of slot_duration
start_time follows 15-minute grid
doctor has no conflicting appointment
patient has no conflicting appointment
```

---

## Appointment Duration

Every appointment must be at least 30 minutes.

The duration must be an integer multiple of the availability's `slot_duration`.

Example:

```text
slot_duration = 30
```

Valid:

```text
30
60
90
120
```

Invalid:

```text
45
75
```

The appointment start time does not need to align to `slot_duration`.

Example:

```text
09:15–10:15
```

is valid for a 30-minute slot duration if all other rules are satisfied.

---

## Start-Time Grid

Allowed start minutes:

```text
00
15
30
45
```

Invalid examples:

```text
09:10
09:12
09:17
09:37
```

---

## Availability Containment

Appointment must be completely contained within one availability.

Valid:

```text
Availability: 09:00–12:00
Appointment: 10:15–11:15
```

Invalid:

```text
08:45–09:45
11:30–12:15
```

An appointment may not span separate availability periods.

---

## Conflict Detection

Use half-open intervals:

```text
[start_time, end_time)
```

Conflict exists when intervals intersect.

Adjacent appointments are allowed:

```text
09:00–09:30
09:30–10:00
```

Conflict:

```text
09:00–09:30
09:15–09:45
```

Check both:

```text
same doctor
same patient
```

---

## State Transitions

Allowed:

```text
pending → confirmed
pending → cancelled
confirmed → completed
confirmed → cancelled
```

Terminal:

```text
completed
cancelled
```

No transition from terminal states.

---

## Cancellation

`cancellation_reason` must be supplied when an appointment becomes cancelled.

For non-cancelled appointments it must remain null.

For:

```text
confirmed → cancelled
```

the appointment start must be at least 24 hours in the future.

Boundary:

```text
>= 24 hours → allowed
< 24 hours  → rejected
```

---

## Transactions

Use:

```php
DB::transaction(...)
```

for appointment operations that require atomic multi-step persistence.

---

## Acceptance Criteria

```text
[ ] valid appointment can be created
[ ] unknown doctor rejected
[ ] unknown patient rejected
[ ] past appointment rejected
[ ] <30 minute appointment rejected
[ ] invalid 15-minute start rejected
[ ] invalid duration multiple rejected
[ ] valid off-slot-duration-grid start accepted
[ ] appointment outside availability rejected
[ ] appointment crossing availability rejected
[ ] doctor conflict rejected
[ ] patient conflict rejected
[ ] adjacent appointments accepted
[ ] pending → confirmed works
[ ] pending → cancelled works
[ ] confirmed → completed works
[ ] confirmed → cancelled works when >=24h remain
[ ] confirmed → cancelled rejected when <24h remain
[ ] terminal transitions rejected
[ ] cancellation reason required on cancellation
[ ] cancellation reason rejected for non-cancelled state
[ ] atomic operations use transactions where required
```

---

## Must Not

Do not:

* implement business rules in controllers,
* create repositories,
* introduce CQRS,
* introduce an event-sourcing model,
* introduce unnecessary abstractions,
* implement unrelated endpoints.

---

## Verification Checklist

Run comprehensive feature tests for:

```text
[ ] creation
[ ] containment
[ ] duration
[ ] grid
[ ] conflicts
[ ] adjacency
[ ] transitions
[ ] cancellation
[ ] 24-hour exact boundary
[ ] 24-hour minus one minute
[ ] UTC handling
```

Run:

```text
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
```

Commit:

```text
feat: implement appointment domain
```

---

# MILESTONE 5 — REST API Layer

## Objective

Expose the implemented domain through the documented REST API.

## Scope

Implement:

* API routes,
* controllers,
* FormRequests,
* API Resources,
* pagination,
* filtering,
* error handling,
* HTTP status codes.

Base path:

```text
/api/v1
```

---

## API Resources

Use Laravel API Resources for:

```text
Doctor
Patient
Availability
Appointment
Available Slot
```

Never return Eloquent models directly as the public API contract.

---

## Controllers

Controllers must:

1. receive request,
2. invoke FormRequest validation,
3. call service,
4. return Resource/JSON response.

Controllers must remain thin.

---

## FormRequests

FormRequests handle:

* required fields,
* types,
* formats,
* basic ranges,
* basic constraints.

Business rules remain in services.

---

## Pagination

All collection endpoints defined as paginated must support:

```text
page
per_page
```

Final values:

```text
default = 25
maximum = 100
```

Backend-controlled deterministic ordering is required.

Do not allow arbitrary client-defined ordering unless explicitly specified by the API contract.

---

## API Filtering

Implement the filters defined by `04-API-SPEC.md`, including as applicable:

```text
doctor
date
date range
patient
appointment status
```

Do not invent additional filters.

---

## Patient Appointments

Expose the documented endpoint for listing appointments belonging to a patient.

Support appointment status filtering.

Pagination is required.

---

## Available Slots

Expose the documented endpoint for available slots.

Support:

* doctor filtering,
* date/range filtering,
* pagination.

Use `SlotService`.

---

## Error Handling

Use structured JSON errors.

Validation errors should follow Laravel's standard validation format unless `04-API-SPEC.md` explicitly defines another contract.

Business-rule violations must use the status/error structure defined by the API specification.

Do not expose stack traces or internal implementation details.

---

## Acceptance Criteria

```text
[ ] all documented routes exist
[ ] all routes use /api/v1
[ ] correct HTTP verbs are used
[ ] FormRequests validate HTTP input
[ ] controllers remain thin
[ ] services contain business logic
[ ] API Resources are used
[ ] pagination works
[ ] default per_page = 25
[ ] maximum per_page = 100
[ ] deterministic ordering exists
[ ] filters work
[ ] validation errors are structured
[ ] business errors are structured
[ ] HTTP status codes match API specification
```

---

## Must Not

Do not add:

* authentication,
* authorization,
* API version other than `/api/v1`,
* frontend,
* GraphQL,
* undocumented endpoints,
* undocumented query parameters.

---

## Verification Checklist

Use HTTP-level feature tests for every endpoint.

Verify:

```text
[ ] successful responses
[ ] validation failures
[ ] not-found responses
[ ] business-rule failures
[ ] pagination
[ ] filtering
[ ] deterministic ordering
[ ] Resource response shape
[ ] status codes
[ ] JSON content type
```

Run:

```text
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
```

Commit:

```text
feat: implement versioned REST API
```

---

# MILESTONE 6 — Feature Test Completion

## Objective

Build the complete automated feature test suite required by the specification.

This milestone should focus primarily on test coverage and edge cases, not new production functionality.

---

## Test Areas

### Doctor

Test:

```text
[ ] creation
[ ] required fields
[ ] email format
[ ] lowercase normalization
[ ] case-insensitive uniqueness
[ ] persistence
```

### Patient

Test:

```text
[ ] creation
[ ] required fields
[ ] email format
[ ] lowercase normalization
[ ] case-insensitive uniqueness
[ ] phone
```

### Availability

Test:

```text
[ ] valid creation
[ ] past rejection
[ ] invalid start/end
[ ] minimum duration
[ ] overlap rejection
[ ] adjacent acceptance
[ ] unknown doctor
```

### Appointment

Test:

```text
[ ] valid creation
[ ] unknown doctor
[ ] unknown patient
[ ] past start
[ ] minimum duration
[ ] 15-minute grid
[ ] slot_duration multiple
[ ] non-slot-aligned valid start
[ ] outside availability
[ ] crosses availability boundary
[ ] doctor conflict
[ ] patient conflict
[ ] adjacent appointment
```

### Status

Test:

```text
[ ] pending → confirmed
[ ] pending → cancelled
[ ] confirmed → completed
[ ] confirmed → cancelled
[ ] invalid transition
[ ] terminal state
```

### Cancellation

Test:

```text
[ ] reason required
[ ] reason nullable before cancellation
[ ] reason null for non-cancelled status
[ ] exactly 24h allowed
[ ] <24h rejected
```

### Slots

Test:

```text
[ ] slot generation
[ ] occupied slots excluded
[ ] doctor filter
[ ] date filter
[ ] date range
[ ] pagination
```

### Pagination

Test:

```text
[ ] default = 25
[ ] custom page size
[ ] maximum = 100
[ ] deterministic ordering
```

---

## Boundary Testing

The agent must explicitly test boundary conditions.

Examples:

```text
availability starts exactly at appointment start
appointment ends exactly at availability end
appointment starts exactly when another appointment ends
appointment ends exactly when another starts
exactly 24 hours before cancellation
one second/minute less than 24 hours
30-minute appointment
29-minute appointment
duration exactly divisible by slot_duration
duration not divisible by slot_duration
00 minute
15 minute
30 minute
45 minute
invalid minute
```

---

## Acceptance Criteria

```text
[ ] all important project rules have automated tests
[ ] tests are deterministic
[ ] tests do not depend on real current time
[ ] time-sensitive tests use controlled time
[ ] database is reset between tests
[ ] test suite passes from a clean database
[ ] failure cases are covered
[ ] boundary cases are covered
```

---

## Must Not

Do not:

* remove valid existing tests just to make the suite pass,
* weaken assertions,
* test implementation details unnecessarily,
* introduce unrelated production changes merely to increase test count.

---

## Verification Checklist

```text
[ ] php artisan test
[ ] php artisan test --coverage (if configured/available)
[ ] vendor/bin/pint --test
[ ] vendor/bin/phpstan analyse
```

Commit:

```text
test: complete feature coverage
```

---

# MILESTONE 7 — Quality Gate & Integration

## Objective

Validate the entire application as an integrated backend project.

No significant new functionality should be introduced in this milestone.

---

## Scope

Perform:

* full test suite,
* migration verification,
* seed verification,
* API smoke testing,
* static analysis,
* formatting,
* configuration review,
* README verification,
* Git hygiene review.

---

## Clean Installation Test

The project must work from a clean state.

Verify:

```text
composer install
```

then:

```text
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
```

or the equivalent Windows-friendly process.

---

## Database Verification

Verify:

```text
[ ] fresh migration works
[ ] rollback works
[ ] fresh migration works again
[ ] seed works
[ ] foreign keys work
[ ] indexes exist
[ ] unique constraints work
[ ] soft deletes work
```

---

## API Smoke Test

Verify at least:

```text
[ ] doctor creation/listing
[ ] patient creation/listing
[ ] availability creation/listing
[ ] appointment creation/listing
[ ] appointment status update
[ ] cancellation
[ ] available slots
[ ] patient appointments
```

Use actual HTTP requests against the running application.

---

## Quality Tools

Run:

```text
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
```

No known errors should remain.

---

## README

README must document at minimum:

```text
project purpose
requirements
installation
environment setup
SQLite setup
migration
seeding
running the API
running tests
running Pint
running PHPStan
API endpoints
important design decisions
```

---

## Git Review

Verify:

```text
[ ] .env not tracked
[ ] vendor/ not tracked
[ ] no secrets committed
[ ] no debug files
[ ] no temporary files
[ ] no unnecessary generated files
[ ] git status clean
```

---

## Acceptance Criteria

The project must be reproducible from a fresh checkout using the documented setup instructions.

A reviewer should be able to:

```text
clone
install
configure
migrate
seed
run
test
inspect API
```

without undocumented manual steps.

---

## Must Not

Do not add new architecture or speculative features.

Only fix objectively identified integration or quality issues.

---

## Verification Checklist

```text
[ ] clean install
[ ] migration
[ ] seed
[ ] API boot
[ ] complete PHPUnit suite
[ ] Pint
[ ] PHPStan/Larastan
[ ] API smoke tests
[ ] README review
[ ] Git review
```

Commit:

```text
chore: complete integration quality gate
```

---

# MILESTONE 8 — Final Review & Delivery

## Objective

Perform the final implementation review against the complete specification.

This milestone is a specification-compliance audit.

---

# 8.1 Specification Audit

Review every major section of:

```text
PROJECT-SPEC.md
02-DATABASE-SPEC.md
03-ARCHITECTURE.md
04-API-SPEC.md
05-IMPLEMENTATION-SPEC.md
06-SETUP-AND-WORKFLOW.md
```

For every requirement classify it as:

```text
IMPLEMENTED
TESTED
DOCUMENTED
```

or identify an actual remaining issue.

Do not mark a requirement complete merely because related functionality exists.

---

# 8.2 Domain Audit

Verify:

```text
[ ] Doctor
[ ] Patient
[ ] Availability
[ ] Appointment
[ ] AppointmentStatus
```

---

# 8.3 Business Rule Audit

Verify:

```text
[ ] availability future requirement
[ ] availability minimum 30 minutes
[ ] availability overlap
[ ] adjacent availability
[ ] appointment future requirement
[ ] minimum appointment duration
[ ] 15-minute grid
[ ] duration multiple
[ ] availability containment
[ ] doctor conflict
[ ] patient conflict
[ ] half-open interval semantics
[ ] slot generation
[ ] occupied slot handling
[ ] status transitions
[ ] terminal states
[ ] cancellation reason
[ ] 24-hour cancellation boundary
```

---

# 8.4 Data Integrity Audit

Verify:

```text
[ ] foreign keys
[ ] indexes
[ ] database uniqueness
[ ] lowercase email normalization
[ ] soft deletes
[ ] timestamps
[ ] UTC handling
[ ] enum persistence
```

---

# 8.5 API Audit

Verify:

```text
[ ] /api/v1 prefix
[ ] documented endpoints
[ ] HTTP methods
[ ] request validation
[ ] API Resources
[ ] pagination
[ ] default per_page = 25
[ ] maximum per_page = 100
[ ] deterministic ordering
[ ] filters
[ ] structured validation errors
[ ] structured business errors
[ ] correct HTTP status codes
```

---

# 8.6 Code Architecture Audit

Verify:

```text
[ ] controllers are thin
[ ] business logic is in services
[ ] FormRequests contain HTTP validation
[ ] models contain persistence/relationships
[ ] Resources serialize API output
[ ] enums represent fixed domain values
[ ] no unnecessary repository layer
[ ] no CQRS
[ ] no unnecessary DDD
[ ] no unrelated infrastructure
```

---

# 8.7 Quality Audit

Run:

```text
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
```

All must pass.

If a command fails:

1. determine whether the failure is caused by the implementation,
2. fix the implementation,
3. rerun the command,
4. do not suppress the failure merely to achieve a green result.

---

# 8.8 Documentation Audit

README must accurately describe the actual project.

Do not document features that were not implemented.

Do not leave obsolete setup instructions.

Verify:

```text
[ ] requirements
[ ] installation
[ ] environment
[ ] SQLite
[ ] migrations
[ ] seeding
[ ] API startup
[ ] tests
[ ] Pint
[ ] PHPStan
[ ] API examples
[ ] design decisions
```

---

# 8.9 Git Audit

Verify:

```text
[ ] meaningful milestone commits
[ ] no accidental secrets
[ ] no .env
[ ] no vendor
[ ] no debug artifacts
[ ] no unrelated files
[ ] working tree clean
```

---

# 8.10 Final Acceptance Criteria

The project is ready for delivery only when:

```text
[ ] all required features are implemented
[ ] all important business rules are tested
[ ] all finalized Open Decisions are respected
[ ] API follows the documented contract
[ ] database integrity is enforced
[ ] migrations work from a clean database
[ ] seeders work
[ ] PHPUnit passes
[ ] Pint passes
[ ] PHPStan/Larastan passes
[ ] README is complete
[ ] Git repository is clean
```

---

# 9. Final Agent Report

After completing Milestone 8, provide a concise final report using this structure:

```text
## Implementation Summary

Implemented the Medicare Backend Test according to the project specifications.

## Milestones

- M1 — Laravel Foundation: COMPLETE
- M2 — Database & Models: COMPLETE
- M3 — Availability & Slots: COMPLETE
- M4 — Appointment Domain: COMPLETE
- M5 — REST API: COMPLETE
- M6 — Feature Tests: COMPLETE
- M7 — Quality Gate: COMPLETE
- M8 — Final Review: COMPLETE

## Verification

PHPUnit:
PASS

Pint:
PASS

PHPStan/Larastan:
PASS

Database migration:
PASS

Database seeding:
PASS

API smoke tests:
PASS

## Git

Working tree:
CLEAN

Final commit:
<commit hash/message>

## Remaining Issues

None

or list only objectively remaining issues.
```

Do not claim completion if any mandatory verification fails.

---

# 10. Agent Execution Protocol

When instructed:

```text
Implement Milestone N.
```

the agent must follow this exact workflow:

```text
1. Read relevant specifications
2. Inspect current repository
3. Identify milestone scope
4. State implementation plan
5. Implement only milestone scope
6. Add/update tests
7. Run tests
8. Run Pint
9. Run PHPStan/Larastan
10. Fix introduced issues
11. Re-run verification
12. Review Git diff
13. Create focused commit
14. Report result
```

The agent must not automatically continue to the next milestone.

The next milestone starts only after an explicit instruction.

---

# 11. Final Rule

The implementation must optimize for:

```text
correctness
clarity
testability
database integrity
API consistency
maintainability
```

It must not optimize for:

```text
maximum abstraction
maximum number of classes
maximum number of packages
enterprise architecture
premature scalability
```

This is a backend coding test.

The final implementation should be small enough to review comfortably while being complete enough to demonstrate solid Laravel, API, database, validation, testing, and business-rule implementation skills.
