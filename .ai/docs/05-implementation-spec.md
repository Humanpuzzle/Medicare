# Medicare Backend Test — Implementation Specification

## 1. Purpose

This document defines the implementation rules and technical conventions for the Medicare Backend Test project.

It translates the finalized project requirements into concrete Laravel implementation guidance.

This document must be read together with:

* `PROJECT-SPEC.md`
* `02-DATABASE-SPEC.md`
* `03-ARCHITECTURE-SPEC.md`
* `04-API-SPEC.md`

The documents have the following responsibility:

| Document                    | Responsibility                                     |
| --------------------------- | -------------------------------------------------- |
| `PROJECT-SPEC.md`           | Functional requirements and final decisions        |
| `02-DATABASE-SPEC.md`       | Database schema and persistence constraints        |
| `03-ARCHITECTURE-SPEC.md`   | Application structure and architectural boundaries |
| `04-API-SPEC.md`            | HTTP endpoints and API contract                    |
| `05-IMPLEMENTATION-SPEC.md` | Concrete implementation rules                      |

If an implementation detail conflicts with a final decision in `PROJECT-SPEC.md`, the final project decision takes precedence.

---

# 2. Implementation Goals

The implementation must prioritize:

1. Correct domain behavior.
2. Correct validation.
3. Database integrity.
4. Consistent API behavior.
5. Clear separation of responsibilities.
6. Automated test coverage.
7. Static-analysis compatibility.
8. Maintainable Laravel conventions.
9. Minimal unnecessary abstraction.

The project is intentionally a focused coding-test implementation.

Do not introduce architectural complexity that is not required by the specification.

---

# 3. Technology Stack

The implementation uses:

* PHP 8.3+
* Laravel 11+
* SQLite
* Eloquent ORM
* Laravel HTTP API
* PHPUnit
* Laravel Pint
* PHPStan
* Larastan

The application is backend-only.

The following are explicitly outside the implementation:

* authentication
* authorization
* users/login
* frontend applications
* Blade UI
* Livewire
* Inertia
* React/Vue
* queues
* event buses
* microservices
* CQRS
* Event Sourcing
* Repository pattern
* Unit of Work pattern
* full DDD framework
* unnecessary generic abstractions
* Laravel Boost

---

# 4. Application Structure

The implementation should follow this structure:

```text
app/
├── Enums/
│   └── AppointmentStatus.php
│
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   ├── Requests/
│   └── Resources/
│
├── Models/
│   ├── Doctor.php
│   ├── Patient.php
│   ├── Availability.php
│   └── Appointment.php
│
├── Rules/
│
└── Services/
    ├── AvailabilityService.php
    ├── SlotService.php
    └── AppointmentService.php
```

Additional classes may be introduced only when they represent a clearly justified responsibility required by the implementation.

Controllers must remain thin.

Business rules must not be duplicated across controllers.

---

# 5. Models

## 5.1 General Model Rules

All domain models must:

* use `declare(strict_types=1);`
* define appropriate casts
* define relationships explicitly
* use mass-assignment protection
* use `SoftDeletes` where required by the database specification
* avoid business logic that belongs in services

Example model style:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Doctor extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'specialty',
    ];
}
```

The exact `$fillable` definition may be adjusted according to the implemented write operations.

---

# 6. Soft Deletes

The project uses soft deletes.

Domain models that are defined as soft-deletable in `02-DATABASE-SPEC.md` must:

* use Laravel's `SoftDeletes` trait
* have a nullable `deleted_at` column
* normally exclude deleted records from application queries
* not expose deleted records through standard API endpoints unless explicitly required

Eloquent's default behavior should be used rather than manually adding `deleted_at IS NULL` conditions everywhere.

For example:

```php
Doctor::query()->find($id);
```

must not return a soft-deleted doctor.

Queries requiring deleted historical records must explicitly opt into them with Eloquent's supported mechanisms.

---

# 7. Relationships

The four core models must expose their domain relationships.

## Doctor

```text
Doctor
 ├── hasMany Availability
 └── hasMany Appointment
```

## Patient

```text
Patient
 └── hasMany Appointment
```

## Availability

```text
Availability
 └── belongsTo Doctor
```

## Appointment

```text
Appointment
 ├── belongsTo Doctor
 └── belongsTo Patient
```

Relationship definitions belong to the models.

Relationship loading must be intentional.

API resources must avoid accidental N+1 queries.

Where a collection endpoint returns related data, eager loading should be used where necessary.

---

# 8. Appointment Status Enum

Appointment statuses must be represented by a backed PHP enum.

```php
enum AppointmentStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
```

The `Appointment` model should cast the database status to this enum.

Application code should therefore prefer:

```php
AppointmentStatus::Pending
```

over raw string literals.

The database representation remains the string value defined by the enum.

---

# 9. Appointment State Machine

Allowed transitions are:

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

Invalid transitions must be rejected.

Examples:

```text
pending -> completed       invalid
completed -> confirmed     invalid
completed -> cancelled     invalid
cancelled -> confirmed     invalid
cancelled -> completed     invalid
```

The transition logic belongs in the appointment business layer, not in the controller.

A transition should be explicitly checked against the current state.

Do not implement state changes by blindly assigning arbitrary status values.

---

# 10. Cancellation Rules

Cancellation follows the finalized project decisions.

A `confirmed` appointment may be cancelled when:

```text
time_until_start >= 24 hours
```

Exactly 24 hours before the appointment is therefore valid.

Less than 24 hours before the appointment must be rejected.

Only the appropriate appointment states may be cancelled according to the state machine.

## Cancellation reason

`cancellation_reason` is:

* nullable
* optional
* allowed for cancelled appointments
* not mandatory for cancellation

The implementation must follow `DEC-003`.

This overrides any earlier wording in the project documentation that described the reason as mandatory.

For non-cancelled appointments the field should normally remain `NULL`.

---

# 11. Date and Time Handling

The application uses **UTC internally** according to `DEC-005`.

All persisted appointment and availability timestamps must therefore be handled consistently in UTC.

Use Laravel/Carbon date objects rather than manually manipulating date strings.

Prefer immutable date/time objects where practical.

Example:

```php
CarbonImmutable
```

should be preferred for calculations where mutation is not required.

The implementation must avoid mixing local application time and UTC internally.

API input/output conversion, if required by the API contract, must follow `04-API-SPEC.md`.

---

# 12. Email Normalization

Doctor and patient email addresses are case-insensitive.

Before persistence:

```text
User@Example.com
```

must become:

```text
user@example.com
```

Normalization must occur before the uniqueness check and before persistence.

The database must also enforce uniqueness as specified in `02-DATABASE-SPEC.md`.

Application-level checks alone are insufficient because they cannot prevent race-condition duplicates.

The normalized value is therefore the canonical stored representation.

---

# 13. Validation Architecture

Validation is divided into two layers.

## 13.1 Form Requests

Form Requests handle HTTP/input validation.

Examples:

* required fields
* data types
* string lengths
* email syntax
* enum input
* integer ranges
* date format
* basic required relationships

Form Requests should not become the primary location for complex cross-entity business rules.

---

## 13.2 Services

Services handle business rules such as:

* availability overlap
* appointment containment
* appointment conflicts
* state transitions
* cancellation timing
* duration rules
* booking constraints
* cross-model validation

This separation keeps controllers and request classes focused.

---

# 14. Availability Implementation

Availability creation and update must enforce:

1. The doctor exists.
2. `starts_at < ends_at`.
3. The availability starts in the future.
4. `slot_duration >= 30 minutes`.
5. The availability does not overlap another availability of the same doctor.
6. Adjacent availability periods are allowed.

The overlap condition is:

```text
existing.starts_at < requested.ends_at
AND
existing.ends_at > requested.starts_at
```

This means:

```text
09:00–10:00
10:00–11:00
```

is valid.

But:

```text
09:00–10:00
09:30–11:00
```

is invalid.

---

# 15. AvailabilityService

`AvailabilityService` is responsible for availability business logic.

Typical responsibilities:

```text
create()
update()
delete()
validatePeriod()
ensureNoOverlap()
```

The service must not duplicate HTTP concerns.

For example, it should not return `JsonResponse`.

It should either:

* perform the operation successfully, or
* raise/use the application's established validation/business-rule error mechanism.

The controller converts the result into the API response.

---

# 16. Appointment Creation

Appointment creation is one of the most important business operations.

It must execute the complete validation process before creating the appointment.

The implementation flow should be conceptually:

```text
1. Validate request structure.
2. Begin database transaction.
3. Resolve patient.
4. Resolve doctor.
5. Validate appointment timestamps.
6. Validate future start.
7. Validate 15-minute start grid.
8. Find a suitable availability.
9. Validate full containment inside one availability.
10. Validate appointment duration.
11. Check doctor conflicts.
12. Check patient conflicts.
13. Create appointment.
14. Commit transaction.
15. Return created appointment.
```

The complete operation must be atomic.

Use:

```php
DB::transaction(...)
```

for the multi-step write.

---

# 17. Appointment Time Rules

Every appointment must:

* start in the future
* be at least 30 minutes long
* use a start time on the 15-minute grid
* be fully contained in one doctor's availability period
* have a duration that is an integer multiple of that availability's `slot_duration`

Valid start minutes are:

```text
00
15
30
45
```

For example:

```text
09:00
09:15
09:30
09:45
```

are valid.

---

# 18. Appointment Duration

Appointment duration is **not globally fixed to 30 minutes**.

The minimum duration is:

```text
30 minutes
```

The duration must also be an integer multiple of the selected availability's `slot_duration`.

For example, for:

```text
slot_duration = 30 minutes
```

valid durations include:

```text
30
60
90
120
...
```

For:

```text
slot_duration = 45 minutes
```

valid durations include:

```text
45
90
135
...
```

The appointment must never extend beyond the availability's `ends_at`.

---

# 19. Appointment Containment

An appointment must be completely contained within one availability period.

Valid:

```text
Availability: 09:00–12:00
Appointment:  10:00–11:00
```

Invalid:

```text
Availability: 09:00–12:00
Appointment:  11:30–12:30
```

An appointment must not be assembled from two separate availability periods.

The implementation must therefore find a single availability satisfying:

```text
availability.starts_at <= appointment.start_time
AND
availability.ends_at >= appointment.end_time
```

for the same doctor.

---

# 20. Appointment Conflict Detection

Appointments use half-open interval semantics:

```text
[start_time, end_time)
```

Two appointments conflict when:

```text
existing.start_time < requested.end_time
AND
existing.end_time > requested.start_time
```

Therefore adjacent appointments are allowed.

Example:

```text
09:00–10:00
10:00–11:00
```

does not conflict.

But:

```text
09:00–10:00
09:59–11:00
```

does conflict.

Conflict detection must be performed independently for:

* the doctor
* the patient

Blocking appointments are active appointments such as:

```text
pending
confirmed
```

Cancelled appointments must not block a new appointment.

Completed appointments are also not considered active booking conflicts.

When updating an appointment, the current appointment must be excluded from its own conflict query.

---

# 21. AppointmentService

`AppointmentService` owns appointment business operations.

Responsibilities include:

```text
create()
update()
confirm()
complete()
cancel()
validateTransition()
validateCancellationWindow()
ensureDoctorConflictFree()
ensurePatientConflictFree()
```

The exact public methods may follow the controller/API design, but business rules must remain centralized here.

The service should not contain presentation concerns.

---

# 22. Slot Generation

`SlotService` is responsible for calculating available booking times.

Its input includes:

* doctor
* date/range
* applicable availability periods
* existing appointments

The generated slots must respect:

* availability boundaries
* `slot_duration`
* future-only requirements where applicable
* existing appointments
* appointment start-grid rules

Existing appointments must be taken into account when determining whether a generated booking time remains available.

---

# 23. Slot Generation and 15-Minute Start Grid

The project contains two related concepts:

1. `slot_duration` defines the base appointment duration unit.
2. Appointment starts use the 15-minute grid.

The implementation must preserve the finalized `DEC-002` rule.

Therefore an appointment may start on a valid 15-minute grid even when that start is not equal to the availability's `slot_duration` increment.

Example:

```text
Availability: 09:00–12:00
slot_duration: 30 minutes

Appointment:
09:15–10:15
```

is valid.

This means slot generation must not incorrectly reject every start that is not aligned to `slot_duration`.

The generated-slot representation and appointment validation must therefore be treated as related but distinct concerns.

The appointment creation rules in Section 17 remain authoritative for accepted appointment starts.

---

# 24. Patient Appointment Queries

Patient appointment listing must:

* filter by the requested patient
* optionally filter by status
* use pagination
* use deterministic backend ordering
* return the API resource defined in `04-API-SPEC.md`

The controller must not manually assemble pagination metadata.

Use Laravel's paginator and API resource mechanisms.

---

# 25. Doctor Appointment and Availability Queries

Doctor-specific queries must always scope records through the doctor relationship or doctor foreign key.

Do not retrieve all records and filter them in PHP.

Prefer database-level filtering:

```php
Appointment::query()
    ->where('doctor_id', $doctorId)
```

over collection-level filtering.

The same principle applies to availability queries.

---

# 26. Pagination

Pagination follows `DEC-007`.

Default:

```text
per_page = 25
```

Maximum:

```text
per_page = 100
```

The API must reject invalid values according to the contract in `04-API-SPEC.md`.

Pagination must be performed by the database query.

Do not load the entire dataset into memory before paginating.

Ordering must be deterministic.

Where an ordering column is not unique, a stable secondary ordering by `id` should be used.

Example:

```text
ORDER BY start_time ASC, id ASC
```

The exact resource-specific ordering must follow `04-API-SPEC.md`.

---

# 27. API Resources

API Resources are responsible for transforming domain models into the documented JSON representation.

Use dedicated resources for:

* Doctor
* Patient
* Availability
* Appointment
* Available Slot

Resources must not perform business calculations that belong in services.

They should primarily:

* expose documented fields
* format relationships
* expose documented metadata
* preserve the API contract

Avoid exposing internal database fields that are not part of the API contract.

---

# 28. Controllers

Controllers must remain thin.

A typical controller flow is:

```text
Request
  ↓
FormRequest validation
  ↓
Service
  ↓
Model / transaction
  ↓
Resource
  ↓
JSON response
```

Controllers should not contain:

* availability overlap algorithms
* appointment conflict queries
* cancellation calculations
* state-machine logic
* slot generation
* complex database workflows

If a controller method becomes responsible for business decisions, move those decisions into the appropriate service.

---

# 29. API Versioning

All API endpoints are versioned under:

```text
/api/v1
```

Routes should therefore be grouped accordingly.

Example:

```php
Route::prefix('v1')->group(function (): void {
    // API routes
});
```

The API version must not be hard-coded independently into individual controllers.

---

# 30. Error Handling

Errors must follow the contract defined in `04-API-SPEC.md`.

The implementation must distinguish between:

### Validation errors

Invalid HTTP input.

Typical response:

```text
422 Unprocessable Entity
```

### Not found

Requested doctor, patient, availability, or appointment does not exist.

Typical response:

```text
404 Not Found
```

### Business-rule violations

Examples:

* overlapping availability
* appointment conflict
* invalid state transition
* cancellation inside the 24-hour boundary
* appointment outside availability
* invalid appointment duration

These must use the status and JSON structure defined by `04-API-SPEC.md`.

The same business condition must produce the same API representation regardless of which controller invokes the underlying service.

Do not leak SQL exceptions, stack traces, or internal implementation details through the API.

---

# 31. Transactions

Use database transactions for operations containing multiple dependent writes or reads followed by a critical write.

Appointment creation must be transactional.

Availability operations should use a transaction when the operation requires multiple dependent database changes.

The transaction boundary belongs in the service/application layer rather than the controller.

Example:

```php
return DB::transaction(function () use ($data): Appointment {
    // validation and creation
});
```

Do not create a transaction around every simple read operation.

---

# 32. Database Integrity

Application validation must not be considered a replacement for database constraints.

Important invariants should be enforced at multiple levels where practical:

```text
HTTP validation
      ↓
Business validation
      ↓
Database constraints
```

Examples:

* foreign-key integrity
* unique normalized emails
* required columns
* valid enum/status storage
* soft-delete columns

Complex temporal constraints such as appointment overlap remain application-level business rules.

The exact schema and indexes belong to `02-DATABASE-SPEC.md`.

---

# 33. Concurrency Considerations

Appointment creation contains a potential race condition:

```text
Request A checks availability
Request B checks availability
Request A creates appointment
Request B creates appointment
```

The implementation should therefore perform conflict checking and creation inside one transaction.

The project does not require a large-scale distributed locking architecture.

Do not introduce Redis locks, external coordination systems, or other infrastructure merely for this coding test.

The implementation should use the database transaction and SQLite capabilities appropriate to the project scope.

---

# 34. Query Design

Queries should be executed at the database level whenever possible.

Avoid:

```php
Model::all()->filter(...);
```

for operations that can be represented as SQL.

Prefer:

```php
Model::query()
    ->where(...)
    ->orderBy(...)
    ->paginate(...);
```

Temporal conditions must be expressed using database comparisons rather than loading every record into PHP.

Queries should select only what is needed when there is a clear performance benefit, but premature micro-optimization is unnecessary.

---

# 35. N+1 Prevention

Collection endpoints must not cause unnecessary N+1 queries.

If an API resource accesses:

```text
appointment.patient
appointment.doctor
```

the controller/service query should eager-load the relationships when returning a collection.

Example:

```php
Appointment::query()
    ->with(['patient', 'doctor'])
    ->paginate($perPage);
```

Eager loading must reflect the actual resource requirements.

Do not blindly eager-load every relationship.

---

# 36. Testing Strategy

Feature tests are the primary testing mechanism.

Tests should exercise the application through the HTTP/API boundary wherever practical.

Important business rules must also have focused tests around the responsible service behavior when useful.

The test suite must cover at minimum:

### Doctors

* create doctor
* list doctors
* retrieve doctor
* update doctor
* delete doctor
* duplicate email rejection
* case-insensitive email normalization

### Patients

* create patient
* list patients
* retrieve patient
* update patient
* delete patient
* duplicate email rejection
* case-insensitive email normalization

### Availability

* create availability
* reject past availability
* reject invalid time range
* reject duration below 30 minutes
* reject overlapping periods
* allow adjacent periods
* update availability
* delete availability

### Appointments

* create valid appointment
* reject unknown patient
* reject unknown doctor
* reject past appointment
* reject invalid 15-minute start
* reject appointment outside availability
* reject appointment spanning multiple availability periods
* reject invalid duration
* reject doctor conflict
* reject patient conflict
* allow adjacent appointments
* confirm appointment
* complete confirmed appointment
* cancel confirmed appointment
* allow cancellation exactly 24 hours before start
* reject cancellation below 24 hours
* reject invalid status transitions
* allow nullable cancellation reason

### Available Slots

* generate slots from availability
* exclude occupied slots
* respect availability boundaries
* respect future-only behavior
* handle multiple availability periods
* handle multiple appointments
* return deterministic results

### Pagination

* default `per_page = 25`
* custom page size
* maximum `per_page = 100`
* reject values above the maximum
* deterministic ordering

---

# 37. Time-Dependent Tests

Time-sensitive business rules must not depend on the actual system clock.

Use Laravel/Carbon test-time functionality.

For example:

```php
Carbon::setTestNow(
    CarbonImmutable::parse('2026-01-01 10:00:00 UTC')
);
```

This is particularly important for:

* future validation
* cancellation windows
* available slots
* appointment creation

Tests must explicitly control the current time when testing temporal boundaries.

The exact 24-hour cancellation boundary must be tested.

---

# 38. Database Tests

Tests must use a clean database state.

Laravel's database testing facilities should be used rather than manually deleting records between tests.

The SQLite test configuration must ensure foreign-key enforcement is enabled.

Factories should be used to create repeatable test data.

Seeders may provide demonstration/development data but tests should not depend on production-style seeded state unless explicitly required.

---

# 39. Factory Expectations

Factories should exist for the core domain models where they improve test readability:

```text
DoctorFactory
PatientFactory
AvailabilityFactory
AppointmentFactory
```

Factories should generate internally valid default records.

Tests that intentionally test invalid states should override the relevant attributes explicitly.

Factories must not hide important business conditions.

For example, a test for appointment conflict should make the conflicting interval obvious in the test itself.

---

# 40. Code Style

The implementation follows PSR-12 and Laravel coding conventions.

Use:

```php
declare(strict_types=1);
```

in application PHP files.

Use:

* typed parameters
* typed return values
* typed properties
* constructor property promotion where appropriate
* enums for finite domain values
* `final` classes where inheritance is not intended
* readonly properties selectively where appropriate

Avoid:

* `mixed` without justification
* untyped public APIs
* static helper classes for ordinary business logic
* unnecessary interfaces
* speculative abstractions

The goal is explicit, readable code.

---

# 41. Service Design Rules

Services should represent meaningful application/domain operations.

Good:

```text
AppointmentService::create()
AppointmentService::cancel()
AvailabilityService::create()
SlotService::getAvailableSlots()
```

Avoid generic services such as:

```text
DataService
CommonService
HelperService
Manager
Utility
```

unless there is a concrete, justified responsibility.

Services should not become dumping grounds for unrelated logic.

---

# 42. Form Request Design

Each write endpoint should use a dedicated Form Request where appropriate.

For example:

```text
StoreDoctorRequest
UpdateDoctorRequest

StorePatientRequest
UpdatePatientRequest

StoreAvailabilityRequest
UpdateAvailabilityRequest

StoreAppointmentRequest
UpdateAppointmentRequest
```

Form Requests should validate input shape.

Complex business conditions must remain in services.

Do not perform database-intensive business workflows inside `rules()` methods.

---

# 43. API Route Model Binding

Laravel route model binding may be used for resource retrieval.

However, soft-deleted models must not accidentally become accessible through normal endpoints.

The implementation must preserve Laravel's default soft-delete behavior unless an endpoint explicitly requires deleted records.

Controllers should return the API's documented not-found representation rather than exposing model internals.

---

# 44. Available Slot Calculation

Available slot calculation must operate from the doctor's availability periods and existing appointments.

Conceptually:

```text
Doctor
  ↓
Availability periods
  ↓
Candidate booking times
  ↓
Existing appointments
  ↓
Remove conflicting candidates
  ↓
Future-only filtering
  ↓
Paginate
  ↓
Available Slot Resource
```

The implementation must not mutate appointment or availability records during slot calculation.

Slot calculation is a read operation.

---

# 45. Update Operations

PATCH semantics must be respected according to the API contract.

Only supplied fields should be changed.

Business validation must run against the resulting state, not merely against the individual supplied fields.

For example, when updating:

```text
starts_at
ends_at
```

the overlap check must use the final combined interval.

Likewise, appointment updates must re-check conflicts and containment when relevant fields change.

---

# 46. Deletion Behavior

Delete operations use soft deletion according to the database specification.

Deleting a doctor or patient must not physically remove historical database rows.

The implementation must respect foreign-key and historical-data requirements.

Do not introduce automatic cascading deletion unless explicitly required by `02-DATABASE-SPEC.md`.

Business behavior for deleting entities with existing related records must follow the API specification.

---

# 47. Logging

Normal successful API requests must not produce excessive application logs.

Logging should be used for genuine application failures or useful diagnostic information.

Temporary development debugging code must not remain in the final implementation.

Do not leave:

```php
dd();
dump();
var_dump();
print_r();
```

or ad-hoc debug logging in production code.

---

# 48. Configuration

Configuration must use Laravel's configuration/environment mechanisms.

Do not hard-code:

* database credentials
* application secrets
* environment-specific URLs
* credentials
* machine-specific filesystem paths

The repository must not contain:

```text
.env
```

or other secret configuration files.

`.env.example` should contain the required configuration keys without real secrets.

---

# 49. README Requirements

The final project README must document at minimum:

1. Project purpose.
2. Requirements.
3. Installation.
4. Environment configuration.
5. SQLite database setup.
6. Migration commands.
7. Seeder usage.
8. Starting the application.
9. Running tests.
10. Running PHPStan/Larastan.
11. Running Pint.
12. API endpoint overview.
13. Important design decisions.
14. Relevant project limitations.

Detailed development workflow belongs primarily to:

```text
06-SETUP-AND-WORKFLOW.md
```

---

# 50. Static Analysis

PHPStan with Larastan must be configured and run against the application.

The project should target approximately:

```text
Level 5–6
```

depending on the final Larastan compatibility and project complexity.

The implementation should fix real type-safety issues rather than suppressing them globally.

Avoid broad configuration such as:

```text
ignoreErrors = *
```

or equivalent blanket suppression.

Any necessary suppression should be narrow and documented.

---

# 51. Code Formatting

Laravel Pint must be used for code formatting.

The project should maintain a clean formatting baseline before completion.

Expected workflow:

```bash
php artisan test
vendor/bin/pint
vendor/bin/phpstan analyse
```

The exact PHPStan command may depend on the final configuration.

---

# 52. Implementation Order

The recommended implementation order is:

```text
1. Laravel project bootstrap
2. SQLite configuration
3. Migrations
4. Enums
5. Models and relationships
6. Factories
7. Form Requests
8. API Resources
9. AvailabilityService
10. SlotService
11. AppointmentService
12. Controllers
13. API routes
14. Feature tests
15. Static analysis
16. Formatting
17. README
```

This order minimizes rework because domain and persistence rules are established before the HTTP layer.

---

# 53. Definition of Done

The implementation is considered complete when:

### Domain

* all four entities are implemented
* relationships work correctly
* soft deletes work correctly
* appointment states and transitions are enforced
* availability rules are enforced
* appointment rules are enforced
* conflict detection works
* slot calculation works

### API

* `/api/v1` routes are implemented
* documented request validation works
* documented responses are returned
* pagination works
* errors follow the API specification
* API Resources are used

### Database

* migrations match `02-DATABASE-SPEC.md`
* foreign keys are enforced
* email uniqueness is enforced
* indexes are present as specified
* soft-delete support is present

### Testing

* core CRUD flows are tested
* business rules are tested
* temporal boundaries are tested
* conflicts are tested
* state transitions are tested
* pagination is tested
* error handling is tested

### Quality

* `declare(strict_types=1)` is used
* code follows Laravel/PSR-12 conventions
* Pint passes
* PHPStan/Larastan passes at the configured level
* no debugging statements remain
* no secrets are committed

---

# 54. Final Implementation Principles

The implementation should follow these principles throughout the project:

### 1. Keep controllers thin

HTTP orchestration belongs in controllers; business decisions do not.

### 2. Centralize business rules

A rule should have one authoritative implementation.

### 3. Prefer Laravel conventions

Use Eloquent, Form Requests, API Resources, transactions, pagination, model relationships, factories, and testing facilities instead of rebuilding framework functionality.

### 4. Enforce important invariants at multiple levels

Where practical:

```text
Request validation
        ↓
Business validation
        ↓
Database integrity
```

### 5. Keep the implementation proportional

This is a backend coding test, not a framework-building exercise.

Do not add:

* repositories
* DTO layers
* CQRS
* event sourcing
* unnecessary interfaces
* unnecessary design patterns
* infrastructure that the requirements do not need

### 6. Optimize for correctness and clarity

The most important qualities are:

```text
Correct domain behavior
        ↓
Predictable API behavior
        ↓
Strong tests
        ↓
Database integrity
        ↓
Readable maintainable code
```

The implementation should be easy for another Laravel developer to understand, run, test, and extend without first learning a custom architecture.
