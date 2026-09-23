# 10 — Implementation Plan

## 1. Purpose

This document defines the concrete implementation sequence for the backend test project.

The implementation must follow the previously approved specifications:

1. `01-PROJECT-SPEC.md`
2. `02-DATABASE-SPEC.md`
3. `03-ARCHITECTURE.md`
4. `04-API-SPEC.md`
5. `05-IMPLEMENTATION-SPEC.md`
6. `06-SETUP-AND-WORKFLOW.md`
7. `07-TESTING-SPEC.md`
8. `08-AI-CODING-GUIDELINES.md`
9. `09-MANUAL-SETUP-AND-DEVELOPER-TASKS.md`

This document defines **when and in what order** the implementation should happen.

---

# 2. Implementation Principles

The project must be implemented incrementally.

Each milestone must:

* have a clearly defined scope;
* produce a working project state;
* include appropriate tests;
* pass the quality checks;
* be committed to Git;
* be pushed when the milestone is considered complete.

The implementation should avoid creating all application code in one large operation.

---

# 3. Standard Milestone Workflow

Every milestone follows this workflow:

```text
Read specification
      ↓
Inspect current code
      ↓
Implement
      ↓
Write/update tests
      ↓
Run tests
      ↓
Run PHPStan
      ↓
Run Pint
      ↓
Review diff
      ↓
Commit
      ↓
Push
```

Standard quality commands:

```bash
php artisan test
vendor/bin/phpstan analyse
vendor/bin/pint --test
```

---

# 4. Milestone 00 — Project Initialization

## Objective

Create the basic Laravel application and establish the initial repository state.

## Tasks

* Create Laravel project.
* Configure `.env`.
* Configure SQLite.
* Create SQLite database.
* Generate application key.
* Verify Laravel installation.
* Initialize Git.
* Configure `.gitignore`.
* Install required development dependencies.
* Configure PHPUnit.
* Configure PHPStan/Larastan.
* Configure Pint.

## Verification

```bash
php artisan about
php artisan migrate
php artisan test
vendor/bin/phpstan analyse
vendor/bin/pint --test
```

## Commit

```text
chore: initialize Laravel project
```

---

# 5. Milestone 01 — Database Foundation

## Objective

Implement the database structure defined in `02-DATABASE-SPEC.md`.

## Tasks

Create:

* migrations;
* foreign keys;
* indexes;
* soft-delete columns;
* required constraints.

Implement the required database entities:

* doctors;
* patients;
* availabilities;
* appointments.

Use the exact fields and relationships defined in the database specification.

## Verification

Run:

```bash
php artisan migrate:fresh
```

Then:

```bash
php artisan migrate:status
```

## Tests

At minimum verify:

* migrations execute successfully;
* foreign-key relationships are valid;
* required tables exist;
* soft-delete columns exist.

## Commit

```text
feat: add database schema
```

---

# 6. Milestone 02 — Enums and Domain Constants

## Objective

Replace magic strings with explicit domain types.

## Tasks

Create the required PHP enums.

Most importantly:

```text
AppointmentStatus
```

with:

```text
pending
confirmed
completed
cancelled
```

Use enums throughout the application instead of duplicating raw strings.

## Verification

Run:

```bash
php artisan test
vendor/bin/phpstan analyse
```

## Commit

```text
feat: add domain enums
```

---

# 7. Milestone 03 — Eloquent Models

## Objective

Implement the application's persistence layer.

## Tasks

Create/configure:

* Doctor model;
* Patient model;
* Availability model;
* Appointment model.

Implement:

* relationships;
* casts;
* enum casts;
* SoftDeletes;
* mass-assignment configuration;
* typed relationship methods.

Expected relationship structure must follow `02-DATABASE-SPEC.md`.

## Verification

Create model-level tests where useful.

Verify:

```text
Doctor → availabilities
Doctor → appointments
Patient → appointments
Availability → doctor
Appointment → doctor
Appointment → patient
```

## Commit

```text
feat: add domain models and relationships
```

---

# 8. Milestone 04 — Factories

## Objective

Create reusable test data generation.

## Tasks

Create factories for all primary entities.

Factories must support:

* valid default data;
* explicit relationships;
* controlled dates;
* appointment status states;
* availability ranges.

Factories must not randomly create conflicting data unless a test explicitly requests a conflict.

## Verification

Run factory tests or use Tinker where appropriate.

Example:

```bash
php artisan tinker
```

## Commit

```text
test: add model factories
```

---

# 9. Milestone 05 — Seeders

## Objective

Create a deterministic demonstration dataset.

## Tasks

Implement:

```text
DatabaseSeeder
```

with realistic but deterministic data.

The seed dataset should demonstrate:

* multiple doctors;
* multiple patients;
* future availability;
* appointments;
* different appointment statuses;
* usable appointment slots.

## Verification

```bash
php artisan migrate:fresh --seed
```

Then verify that the expected data exists.

## Commit

```text
feat: add deterministic seed data
```

---

# 10. Milestone 06 — API Foundation

## Objective

Establish the common API architecture before implementing individual resources.

## Tasks

Configure:

```text
/api/v1
```

Implement the common API conventions defined by `04-API-SPEC.md`.

Create the required:

* API Resources;
* FormRequests;
* exception/error handling structure;
* common response behavior.

## Verification

Run:

```bash
php artisan route:list
```

Verify all routes are under the expected API version.

## Commit

```text
feat: add versioned api foundation
```

---

# 11. Milestone 07 — Doctor API

## Objective

Implement the doctor endpoints.

## Tasks

Implement the documented doctor endpoints.

Depending on the API specification this includes:

* list;
* show;
* create;
* update;
* delete.

Use:

```text
FormRequest
    ↓
Action
    ↓
Model
    ↓
Resource
```

where applicable.

## Validation

Test:

* required fields;
* invalid values;
* missing resources;
* update behavior;
* soft deletion.

## Pagination

If the endpoint is paginated:

* implement Laravel pagination;
* implement deterministic ordering;
* test pagination metadata.

## Tests

Minimum:

```text
list doctors
show doctor
create doctor
update doctor
delete doctor
validation failure
not found
soft delete behavior
pagination
```

## Commit

```text
feat: add doctor api
```

---

# 12. Milestone 08 — Patient API

## Objective

Implement the patient endpoints.

## Tasks

Implement all patient endpoints defined in the API specification.

Follow the same architecture as the Doctor API.

## Tests

Minimum:

```text
list patients
show patient
create patient
update patient
delete patient
validation
not found
soft delete behavior
pagination where applicable
```

## Commit

```text
feat: add patient api
```

---

# 13. Milestone 09 — Availability API

## Objective

Implement doctor availability management.

## Tasks

Implement the documented availability endpoints.

The implementation must enforce:

* valid doctor;
* future-only rule;
* valid start/end ordering;
* 15-minute boundaries;
* no overlapping active availability;
* soft-delete behavior.

## 15-Minute Rule

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
09:05
09:12
09:17
09:37
```

## Overlap

The same doctor's active availability periods must not overlap.

Boundary touching is allowed when defined by the specification:

```text
09:00–10:00
10:00–11:00
```

## Tests

Test:

* valid availability;
* invalid time;
* invalid boundary;
* past availability;
* overlapping availability;
* touching availability;
* deleted availability;
* missing doctor.

## Commit

```text
feat: add doctor availability api
```

---

# 14. Milestone 10 — Appointment Domain Logic

## Objective

Implement the core appointment business rules before exposing all appointment endpoints.

This is one of the most important milestones.

## Rules

An appointment must:

* belong to an existing doctor;
* belong to an existing patient;
* be in the future according to the specification;
* use the 15-minute grid;
* fit completely inside doctor availability;
* not conflict with another blocking appointment.

## Blocking statuses

```text
pending
confirmed
```

## Non-blocking statuses

```text
completed
cancelled
soft-deleted
```

## Conflict Types

### Doctor conflict

The same doctor cannot have overlapping blocking appointments.

### Patient conflict

The same patient cannot have overlapping blocking appointments.

### Availability conflict

The appointment must be fully contained inside an active availability period.

## Commit

```text
feat: implement appointment domain rules
```

---

# 15. Milestone 11 — Appointment Creation

## Objective

Implement appointment creation.

## Workflow

```text
Request
  ↓
Validate input
  ↓
Load doctor/patient
  ↓
Validate future time
  ↓
Validate 15-minute boundary
  ↓
Validate availability
  ↓
Check doctor conflict
  ↓
Check patient conflict
  ↓
Create appointment transactionally
  ↓
Return AppointmentResource
```

## Transaction

The final write operation must use an appropriate database transaction.

## Tests

Minimum:

```text
valid creation
doctor not found
patient not found
past appointment
invalid time boundary
outside availability
doctor conflict
patient conflict
valid adjacent appointment
```

## Commit

```text
feat: add appointment creation
```

---

# 16. Milestone 12 — Appointment Listing

## Objective

Implement appointment retrieval endpoints.

This includes the endpoint for:

> listing the appointments of a given doctor.

## Requirements

* pagination;
* fixed backend ordering;
* deterministic results;
* appropriate filtering;
* soft-deleted appointments excluded.

## Tests

Verify:

* correct doctor;
* pagination;
* ordering;
* empty result;
* deleted appointment behavior.

## Commit

```text
feat: add appointment listing api
```

---

# 17. Milestone 13 — Appointment Show

## Objective

Implement individual appointment retrieval.

## Tasks

Implement the documented show endpoint.

Return:

```text
AppointmentResource
```

## Tests

* existing appointment;
* missing appointment;
* soft-deleted appointment.

## Commit

```text
feat: add appointment details api
```

---

# 18. Milestone 14 — Appointment PATCH

## Objective

Implement appointment modification using:

```http
PATCH
```

The PATCH endpoint must follow the previously approved API specification.

## Tasks

Implement only fields that are allowed to change.

Do not allow clients to modify protected/domain-controlled values arbitrarily.

Every modification must re-run the relevant business rules.

For example, changing the appointment time must re-check:

* future-only rule;
* 15-minute boundary;
* availability;
* doctor conflict;
* patient conflict.

## Tests

Test:

* valid PATCH;
* partial update;
* invalid data;
* conflict after update;
* availability violation;
* missing appointment;
* soft-deleted appointment.

## Commit

```text
feat: add appointment patch api
```

---

# 19. Milestone 15 — Appointment Status Transitions

## Objective

Implement the appointment status state machine.

Allowed:

```text
pending
   ├── confirmed
   └── cancelled

confirmed
   ├── completed
   └── cancelled

completed
   └── terminal

cancelled
   └── terminal
```

Invalid transitions must return:

```http
409 Conflict
```

## Tests

Test every valid transition and representative invalid transitions.

## Commit

```text
feat: enforce appointment status transitions
```

---

# 20. Milestone 16 — Appointment Cancellation

## Objective

Implement the cancellation business rule.

The previously defined 24-hour rule must be enforced exactly.

Test:

```text
more than 24 hours
exactly 24 hours
less than 24 hours
```

The test clock must be controlled.

## Tests

At minimum:

* valid cancellation;
* exactly-boundary cancellation;
* too-late cancellation;
* already cancelled;
* completed appointment;
* missing appointment.

## Commit

```text
feat: add appointment cancellation rules
```

---

# 21. Milestone 17 — Slot Generation

## Objective

Implement dynamic appointment slot generation.

## Algorithm

```text
Doctor
  ↓
Active future availability
  ↓
Generate 15-minute slots
  ↓
Remove blocked appointments
  ↓
Return deterministic list
```

Slots must:

* respect availability;
* use 15-minute boundaries;
* ignore soft-deleted availability;
* exclude blocking appointments;
* not expose past slots where the future-only rule excludes them.

## Tests

Test:

* empty availability;
* single availability;
* multiple availabilities;
* boundary slots;
* existing appointment;
* cancelled appointment;
* completed appointment;
* soft-deleted availability;
* multiple doctors.

## Commit

```text
feat: add appointment slot generation
```

---

# 22. Milestone 18 — Error Handling

## Objective

Finalize consistent API error handling.

Required statuses include:

```text
400 / 422
404
409
500
```

according to the exact API specification.

## Rules

Do not expose:

* stack traces;
* SQL errors;
* internal file paths;
* sensitive configuration;
* debugging information.

Business conflicts must be distinguishable from ordinary validation errors.

## Tests

Test:

* validation error;
* not found;
* conflict;
* invalid state transition;
* unexpected exception behavior where practical.

## Commit

```text
feat: standardize api error handling
```

---

# 23. Milestone 19 — Test Hardening

## Objective

Perform a dedicated testing pass after all business logic exists.

Review all important boundaries.

## Required areas

### Time

```text
past
now
future
```

### 15-minute boundaries

```text
00
15
30
45
```

### Availability

```text
before
inside
exact start
exact end
after
overlap
touching boundary
```

### Appointments

```text
overlap
adjacent
same doctor
same patient
different doctor
different patient
```

### Status

```text
pending
confirmed
completed
cancelled
```

### Cancellation

```text
>24h
=24h
<24h
```

### Soft delete

Verify that deleted records no longer participate in active business logic.

## Commit

```text
test: harden appointment business rules
```

---

# 24. Milestone 20 — Static Analysis and Refactoring

## Objective

Bring the codebase to a clean quality state.

Run:

```bash
vendor/bin/phpstan analyse
```

Fix all meaningful issues.

Then:

```bash
vendor/bin/pint
```

Then:

```bash
php artisan test
```

## Review

Look for:

* duplicated logic;
* unnecessary abstractions;
* missing return types;
* untyped properties;
* overly complex methods;
* dead code;
* unused imports;
* unnecessary dependencies.

## Commit

```text
refactor: improve code quality
```

---

# 25. Milestone 21 — README

## Objective

Create the final developer-facing README.

README must include:

## Project

Short description of the assignment.

## Requirements

Required:

* PHP;
* Composer;
* SQLite;
* Git.

## Installation

```bash
composer install
```

## Environment

Explain:

```text
.env
SQLite
APP_KEY
```

## Database

```bash
php artisan migrate:fresh --seed
```

## Tests

```bash
php artisan test
```

## Static analysis

```bash
vendor/bin/phpstan analyse
```

## Formatting

```bash
vendor/bin/pint --test
```

## Run API

```bash
php artisan serve
```

## API examples

Include representative requests for:

* doctors;
* patients;
* availability;
* slots;
* appointments.

## Business rules

Document the most important rules briefly.

## Commit

```text
docs: finalize project documentation
```

---

# 26. Milestone 22 — Full Integration Verification

## Objective

Verify the entire application as a coherent system.

Start from a clean database:

```bash
php artisan migrate:fresh --seed
```

Run:

```bash
php artisan test
```

Then:

```bash
vendor/bin/phpstan analyse
```

Then:

```bash
vendor/bin/pint --test
```

Then:

```bash
php artisan route:list
```

Then:

```bash
php artisan serve
```

Manually verify representative API requests.

## Commit

```text
test: verify complete application
```

---

# 27. Milestone 23 — Fresh Environment Verification

## Objective

Verify that the repository can be used by another developer.

From a fresh checkout:

```bash
composer install
```

Configure:

```text
.env
```

Then:

```bash
php artisan key:generate
php artisan migrate:fresh --seed
php artisan test
vendor/bin/phpstan analyse
vendor/bin/pint --test
```

Start:

```bash
php artisan serve
```

The documented API examples must work.

## Commit

```text
chore: verify fresh setup
```

---

# 28. Milestone 24 — Final Git Review

Before final push:

```bash
git status
```

Then:

```bash
git diff
```

Check that the repository does not contain:

```text
.env
database/*.sqlite
vendor/
node_modules/
debug logs
temporary files
IDE-specific files
credentials
```

Review:

```bash
git log --oneline
```

Commit history should clearly communicate the implementation progression.

---

# 29. Final Quality Gate

The project cannot be considered complete until all commands succeed:

```bash
php artisan migrate:fresh --seed
```

```bash
php artisan test
```

```bash
vendor/bin/phpstan analyse
```

```bash
vendor/bin/pint --test
```

```bash
php artisan route:list
```

The API must start successfully:

```bash
php artisan serve
```

---

# 30. Final Submission Checklist

## Application

* [ ] Laravel application works.
* [ ] SQLite works.
* [ ] Migrations work.
* [ ] Seeders work.
* [ ] API works.
* [ ] API versioning is correct.
* [ ] Pagination works.
* [ ] Ordering is deterministic.

## Domain

* [ ] Doctors work.
* [ ] Patients work.
* [ ] Availability works.
* [ ] Appointments work.
* [ ] Slot generation works.
* [ ] Status transitions work.
* [ ] Cancellation rule works.
* [ ] Soft deletes work.
* [ ] 15-minute rule works.
* [ ] Conflict detection works.

## Quality

* [ ] PHPUnit passes.
* [ ] PHPStan passes.
* [ ] Pint passes.
* [ ] No unnecessary dependencies.
* [ ] No unexplained static-analysis suppressions.
* [ ] No debug code.

## Documentation

* [ ] README complete.
* [ ] Installation documented.
* [ ] Database setup documented.
* [ ] Test commands documented.
* [ ] API examples documented.
* [ ] Business rules documented.

## Git

* [ ] Meaningful milestone commits.
* [ ] No secrets.
* [ ] No generated dependencies committed.
* [ ] Remote configured.
* [ ] Final changes pushed.

---

# 31. Implementation Order Summary

The complete execution order is:

```text
00  Project initialization
 ↓
01  Database foundation
 ↓
02  Enums
 ↓
03  Models / relationships
 ↓
04  Factories
 ↓
05  Seeders
 ↓
06  API foundation
 ↓
07  Doctor API
 ↓
08  Patient API
 ↓
09  Availability API
 ↓
10  Appointment domain rules
 ↓
11  Appointment creation
 ↓
12  Appointment listing
 ↓
13  Appointment show
 ↓
14  Appointment PATCH
 ↓
15  Status transitions
 ↓
16  Cancellation
 ↓
17  Slot generation
 ↓
18  Error handling
 ↓
19  Test hardening
 ↓
20  Static analysis / refactoring
 ↓
21  README
 ↓
22  Integration verification
 ↓
23  Fresh environment verification
 ↓
24  Final Git review
 ↓
SUBMISSION
```

---

# 32. AI Agent Execution Rule

The AI coding agent should execute **one milestone at a time**.

It must not automatically continue through the entire plan without verification.

After each milestone:

```text
IMPLEMENT
   ↓
TEST
   ↓
PHPSTAN
   ↓
PINT
   ↓
REVIEW
   ↓
COMMIT
```

Only after a successful milestone should the next milestone begin.

If an implementation decision is unclear, the agent must stop at that point rather than inventing a new domain rule.

---

# 33. Final Principle

The implementation should remain intentionally small.

The objective is not to demonstrate every Laravel feature.

The objective is to demonstrate:

* clean Laravel architecture;
* correct relational data modeling;
* explicit business rules;
* robust appointment/availability logic;
* REST API design;
* validation;
* error handling;
* automated testing;
* static analysis;
* maintainable code;
* reproducible setup;
* disciplined Git workflow.

The final application should be easy for another developer to understand, install, test, run, and review.
