# Medicare Backend Test — Architecture Specification

## 1. Purpose

This document defines the application architecture and responsibility boundaries for the Medicare Backend Test.

The architecture is intentionally simple and Laravel-native.

The primary goals are:

* clear separation of responsibilities;
* thin controllers;
* centralized business rules;
* predictable service boundaries;
* testable application logic;
* minimal abstraction;
* compatibility with Laravel conventions.

The architecture must support the requirements defined in:

```text
01-PROJECT-SPEC.md
02-DATABASE-SPEC.md
```

The implementation must not introduce architectural complexity that is not required by the assignment.

---

# 2. Architectural Style

The application follows a lightweight layered Laravel architecture:

```text
HTTP Request
     │
     ▼
Route
     │
     ▼
Controller
     │
     ├── FormRequest
     │
     ▼
Service
     │
     ├── Domain/business rules
     ├── Model queries
     └── DB transaction where required
     │
     ▼
Model / Database
     │
     ▼
API Resource
     │
     ▼
JSON Response
```

The preferred request flow is:

```text
Client
  ↓
Route
  ↓
Controller
  ↓
FormRequest
  ↓
Service
  ↓
Model / Query Builder
  ↓
Resource
  ↓
JSON
```

Controllers must remain thin.

Business rules must not be implemented directly in controllers.

---

# 3. Technology Stack

The backend uses:

* PHP 8.3+
* Laravel 11+
* SQLite
* Composer
* PHPUnit
* Laravel Pint
* PHPStan
* Larastan

The project is API-only.

No frontend framework is required.

---

# 4. API Structure

All API routes are versioned under:

```text
/api/v1
```

Examples:

```text
/api/v1/doctors
/api/v1/patients
/api/v1/availabilities
/api/v1/appointments
```

Laravel API routes should be registered in:

```text
routes/api.php
```

The API must remain stateless.

Authentication and authorization are outside the scope of the test.

---

# 5. Directory Structure

The target application structure is:

```text
app/
├── Enums/
│   └── AppointmentStatus.php
│
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       └── V1/
│   │           ├── DoctorController.php
│   │           ├── PatientController.php
│   │           ├── AvailabilityController.php
│   │           └── AppointmentController.php
│   │
│   ├── Requests/
│   │   └── Api/
│   │       └── V1/
│   │           ├── Doctor/
│   │           ├── Patient/
│   │           ├── Availability/
│   │           └── Appointment/
│   │
│   └── Resources/
│       └── Api/
│           └── V1/
│               ├── DoctorResource.php
│               ├── PatientResource.php
│               ├── AvailabilityResource.php
│               ├── AppointmentResource.php
│               └── AvailableSlotResource.php
│
├── Models/
│   ├── Doctor.php
│   ├── Patient.php
│   ├── Availability.php
│   └── Appointment.php
│
├── Services/
│   ├── DoctorService.php
│   ├── PatientService.php
│   ├── AvailabilityService.php
│   ├── SlotService.php
│   └── AppointmentService.php
│
└── Rules/
```

Additional classes should only be introduced when there is a concrete requirement.

---

# 6. Architectural Responsibility Boundaries

## 6.1 Routes

Routes are responsible only for:

* mapping HTTP methods and URLs;
* selecting the appropriate controller;
* applying route model binding where useful.

Routes must not contain business logic.

Example:

```php
Route::apiResource('doctors', DoctorController::class);
```

---

# 7. Controllers

Controllers are responsible for:

* receiving the HTTP request;
* invoking FormRequests;
* passing validated data to services;
* invoking the appropriate service operation;
* returning API Resources;
* returning appropriate HTTP responses.

Controllers must not:

* contain business rules;
* perform complex database queries;
* calculate appointment conflicts;
* calculate availability overlap;
* implement state-transition rules;
* contain transaction orchestration that belongs to a service.

---

## 7.1 Thin Controller Principle

Preferred:

```php
public function store(StoreAppointmentRequest $request): AppointmentResource
{
    $appointment = $this->appointmentService->create(
        $request->validated()
    );

    return new AppointmentResource($appointment);
}
```

Avoid:

```php
public function store(Request $request)
{
    // validation
    // availability lookup
    // conflict detection
    // duration calculation
    // transaction
    // appointment creation
    // response formatting
}
```

The second approach creates an oversized controller and makes business logic difficult to test.

---

# 8. FormRequests

FormRequests are responsible for HTTP/input validation.

They should handle:

* required fields;
* data types;
* basic formats;
* basic ranges;
* enum values;
* basic timestamp formatting;
* pagination parameters;
* basic filtering parameters.

They should not contain complex cross-entity business rules.

---

## 8.1 Example

A request may validate:

```text
doctor_id exists
patient_id exists
start_time is a valid date
end_time is a valid date
```

But it should not determine whether:

```text
the doctor is available
```

or:

```text
another appointment conflicts
```

Those are service/domain responsibilities.

---

# 9. Services

Services contain application-level business orchestration.

The primary services are:

```text
DoctorService
PatientService
AvailabilityService
SlotService
AppointmentService
```

Not every CRUD operation necessarily requires a service method if Laravel's default behavior is sufficient, but business-heavy operations must be centralized.

---

# 10. DoctorService

`DoctorService` is responsible for doctor-related application operations where business orchestration is required.

Potential responsibilities:

* create doctor;
* update doctor;
* delete/soft-delete doctor;
* doctor-specific validation requiring application logic.

Email normalization may be performed here or through a dedicated normalization mechanism.

Example:

```text
John@Example.com
        ↓
john@example.com
```

Database uniqueness remains the final integrity boundary.

---

# 11. PatientService

`PatientService` is responsible for patient-related application operations.

Potential responsibilities:

* create patient;
* update patient;
* delete/soft-delete patient;
* email normalization;
* patient-specific business orchestration.

Basic validation remains in FormRequests.

---

# 12. AvailabilityService

`AvailabilityService` owns availability business rules.

Responsibilities include:

* create availability;
* update availability;
* delete/soft-delete availability;
* validate future availability;
* validate start/end ordering;
* validate minimum duration;
* validate `slot_duration`;
* detect overlap;
* ensure doctor exists;
* query doctor availability.

---

## 12.1 Availability Overlap

Availability periods for the same doctor must not overlap.

Interval semantics:

```text
[start, end)
```

Conflict condition:

```text
existing.starts_at < new.ends_at
AND
existing.ends_at > new.starts_at
```

Adjacent intervals are valid.

Example:

```text
09:00–10:00
10:00–11:00
```

is allowed.

---

## 12.2 Availability Update

When updating an availability, the current record must be excluded from the overlap check.

Conceptually:

```text
find overlapping active availability
where doctor_id = current doctor
and id != current availability
```

---

# 13. SlotService

`SlotService` is responsible for calculating available appointment slots.

It should not create appointments.

Responsibilities:

* generate slots from availability;
* apply `slot_duration`;
* account for existing appointments;
* support doctor/date/range filtering;
* return slot data suitable for API serialization.

---

# 14. Slot Generation

Given:

```text
Availability:
09:00–11:00

slot_duration:
30
```

the generated base slots are:

```text
09:00–09:30
09:30–10:00
10:00–10:30
10:30–11:00
```

These represent bookable start points.

They do not imply that every appointment must be exactly 30 minutes.

---

# 15. Slot and Appointment Relationship

The system distinguishes between:

```text
generated slot
```

and:

```text
appointment
```

A slot represents a possible appointment start.

An appointment may consume multiple `slot_duration` units.

Example:

```text
slot_duration = 30

Appointment:
09:15–10:15
```

is valid if:

* `09:15` is on the 15-minute grid;
* duration is 60 minutes;
* availability contains the full interval;
* no conflict exists.

---

# 16. Available Slot Filtering

Existing active appointments must affect slot availability.

A generated slot should not be returned as available when the corresponding booking interval conflicts with an existing appointment.

The exact slot response and filtering contract is defined in:

```text
04-API-SPEC.md
```

The service must not duplicate response-formatting logic that belongs to the Resource layer.

---

# 17. AppointmentService

`AppointmentService` is the primary business service of the application.

Responsibilities include:

* appointment creation;
* appointment update where supported;
* appointment listing;
* appointment status changes;
* appointment cancellation;
* availability containment;
* duration validation;
* 15-minute grid validation;
* doctor conflict detection;
* patient conflict detection;
* state transition validation;
* cancellation boundary validation;
* cancellation reason validation;
* transaction handling.

---

# 18. Appointment Creation Flow

Appointment creation follows this conceptual flow:

```text
HTTP request
     ↓
FormRequest
     ↓
AppointmentService
     ↓
Validate doctor
     ↓
Validate patient
     ↓
Validate future appointment
     ↓
Validate start-time grid
     ↓
Find containing availability
     ↓
Validate minimum duration
     ↓
Validate duration / slot_duration
     ↓
Check doctor conflict
     ↓
Check patient conflict
     ↓
Create appointment
     ↓
Commit transaction
     ↓
AppointmentResource
```

---

# 19. Appointment Transaction

Appointment creation should be executed within a database transaction where multiple reads and writes must form one atomic operation.

Conceptually:

```php
DB::transaction(function () {
    // validate relevant scheduling state
    // perform conflict checks
    // create appointment
});
```

If appointment creation fails, the transaction must not leave partially-created data.

---

# 20. Appointment Future Rule

An appointment's start time must be in the future.

The comparison must use the application's time handling rules.

Internal persistence uses UTC.

Business-level interpretation uses:

```text
Europe/Budapest
```

The implementation must avoid mixing local and UTC values during comparisons.

---

# 21. Appointment Minimum Duration

Every appointment must be at least:

```text
30 minutes
```

Therefore:

```text
end_time - start_time >= 30 minutes
```

This is a service-level business rule.

---

# 22. Appointment Start Grid

Appointment start times must use:

```text
00
15
30
45
```

minute values.

Examples:

```text
09:00
09:15
09:30
09:45
```

are valid.

Examples:

```text
09:10
09:12
09:17
```

are invalid.

This rule belongs to appointment business validation.

---

# 23. Appointment Duration and Slot Duration

Appointment duration must be an integer multiple of the availability's `slot_duration`.

Example:

```text
slot_duration = 30

30 minutes → valid
60 minutes → valid
90 minutes → valid
```

But:

```text
45 minutes → invalid
75 minutes → invalid
```

The appointment start does not have to align to the slot duration.

Therefore:

```text
09:15–10:15
```

is valid for:

```text
slot_duration = 30
```

provided all other rules are satisfied.

---

# 24. Availability Containment

An appointment must be fully contained within one availability.

Conceptually:

```text
availability.starts_at <= appointment.start_time
AND
availability.ends_at >= appointment.end_time
```

The availability must belong to the selected doctor.

An appointment must not span across multiple availability periods.

---

# 25. Appointment Conflict Detection

Two appointment intervals conflict when:

```text
existing.start_time < new.end_time
AND
existing.end_time > new.start_time
```

The interval model is:

```text
[start_time, end_time)
```

Therefore:

```text
09:00–09:30
09:30–10:00
```

do not conflict.

But:

```text
09:00–09:30
09:15–09:45
```

do conflict.

---

# 26. Doctor Conflict

The service must reject an appointment when the same doctor has another active conflicting appointment.

Query concept:

```text
where doctor_id = selected doctor
and active appointment
and existing.start_time < requested.end_time
and existing.end_time > requested.start_time
```

---

# 27. Patient Conflict

The service must reject an appointment when the same patient has another active conflicting appointment.

Query concept:

```text
where patient_id = selected patient
and active appointment
and existing.start_time < requested.end_time
and existing.end_time > requested.start_time
```

---

# 28. Active Appointment States

For future scheduling conflict checks, the following statuses represent active bookings:

```text
pending
confirmed
```

The following do not block future bookings:

```text
completed
cancelled
```

Soft-deleted appointments also do not participate in normal conflict checks.

---

# 29. Appointment Status Enum

The status is represented by:

```text
App\Enums\AppointmentStatus
```

Allowed values:

```text
pending
confirmed
completed
cancelled
```

The enum should be a backed string enum.

Example:

```php
enum AppointmentStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
```

---

# 30. Status Transition Rules

Allowed transitions:

```text
pending   → confirmed
pending   → cancelled

confirmed → completed
confirmed → cancelled
```

Terminal states:

```text
completed
cancelled
```

No transition is allowed from a terminal state.

The transition rules belong to `AppointmentService`.

---

# 31. Cancellation

Cancellation is a business operation, not a simple status update.

The service must validate:

1. appointment exists;
2. appointment is in a cancellable state;
3. cancellation time satisfies the 24-hour rule;
4. cancellation reason follows the finalized project rule;
5. status is changed atomically.

The finalized project decision states:

```text
cancellation_reason is optional and nullable
```

Therefore the service must not introduce an artificial mandatory requirement unless `04-API-SPEC.md` explicitly defines one later.

---

# 32. Cancellation Time Boundary

Cancellation is allowed when:

```text
time_until_start >= 24 hours
```

Therefore:

```text
24 hours remaining → allowed
less than 24 hours → rejected
```

This rule applies to:

```text
confirmed → cancelled
```

The service must calculate the difference using the canonical application time representation.

---

# 33. Update Operations

HTTP update operations should use:

```text
PATCH
```

where partial updates are required.

Controllers should pass validated update data to the corresponding service.

The service is responsible for determining whether the requested update is compatible with existing business rules.

An appointment update must not bypass:

* availability validation;
* duration rules;
* conflict detection;
* status rules.

---

# 34. API Resources

All API responses representing domain entities must use Laravel API Resources.

Resources include:

```text
DoctorResource
PatientResource
AvailabilityResource
AppointmentResource
AvailableSlotResource
```

Controllers should not return raw Eloquent models.

---

# 35. Resource Responsibility

Resources are responsible for:

* response serialization;
* field selection;
* relationship representation;
* consistent JSON structure.

Resources must not:

* perform business validation;
* create records;
* update records;
* perform scheduling decisions.

---

# 36. Pagination

Collection endpoints use Laravel's standard page-based pagination.

Finalized values:

```text
default per_page = 25
maximum per_page = 100
```

Supported parameters:

```text
page
per_page
```

Example:

```text
/api/v1/appointments?page=2&per_page=25
```

The backend controls deterministic ordering.

Controllers/services should explicitly define ordering rather than relying on unspecified database row order.

---

# 37. Filtering

Filtering belongs to the query/application layer.

Examples include:

```text
doctor_id
patient_id
status
date/range
```

Filtering must be validated at the HTTP boundary where appropriate.

Business rules should not be implemented inside query-string parsing code.

---

# 38. Patient Appointment Listing

The API supports listing appointments belonging to a patient.

The service/query layer is responsible for:

* patient filtering;
* optional status filtering;
* deterministic ordering;
* pagination.

The endpoint response uses:

```text
AppointmentResource
```

---

# 39. Doctor Appointment Listing

The API supports listing appointments belonging to a doctor.

The service/query layer is responsible for:

* doctor filtering;
* date/range filtering where supported;
* deterministic ordering;
* pagination.

---

# 40. Available Slots

Available slot generation belongs to:

```text
SlotService
```

The service should:

1. retrieve relevant active availabilities;
2. generate base slots from `slot_duration`;
3. retrieve relevant active appointments;
4. remove blocked slots;
5. return normalized slot data.

The service should not format the final JSON response.

That responsibility belongs to:

```text
AvailableSlotResource
```

---

# 41. Query Responsibilities

Complex queries should live in the service/query layer rather than controllers.

The initial implementation does not require a Repository Pattern.

Direct Eloquent queries are acceptable:

```php
Doctor::query()
    ->where(...)
    ->orderBy(...)
    ->paginate(...);
```

The architecture intentionally avoids introducing:

```text
Repository
DAO
Query Object
Specification Pattern
```

unless a concrete implementation need emerges.

---

# 42. Models

Models are responsible for:

* persistence;
* relationships;
* casts;
* mass-assignment configuration;
* simple model behavior.

Models should not become large domain-service containers.

Complex business operations belong in services.

---

# 43. Doctor Model

Responsibilities:

* doctor persistence;
* relationships;
* soft deletes;
* attributes/casts if required.

Relationships:

```text
hasMany(Availability)
hasMany(Appointment)
```

---

# 44. Patient Model

Responsibilities:

* patient persistence;
* relationships;
* soft deletes;
* attributes/casts if required.

Relationship:

```text
hasMany(Appointment)
```

---

# 45. Availability Model

Responsibilities:

* availability persistence;
* relationship to doctor;
* datetime casts;
* soft deletes.

Relationship:

```text
belongsTo(Doctor)
```

---

# 46. Appointment Model

Responsibilities:

* appointment persistence;
* relationships;
* datetime casts;
* AppointmentStatus enum cast;
* soft deletes.

Relationships:

```text
belongsTo(Doctor)
belongsTo(Patient)
```

---

# 47. Validation vs Business Logic

The following division must be maintained.

## FormRequest

Examples:

```text
required
string
integer
date
exists
nullable
enum
max
min
```

## Service

Examples:

```text
availability overlap
availability containment
appointment conflict
future appointment
minimum appointment duration
duration multiple
15-minute start grid
state transition
24-hour cancellation rule
```

This separation makes business logic reusable outside a specific HTTP request.

---

# 48. Database Constraints vs Services

Critical relational integrity belongs in the database where practical.

Database:

```text
primary keys
foreign keys
NOT NULL
email uniqueness
```

Services:

```text
temporal rules
overlap rules
scheduling rules
state transitions
cancellation rules
```

This follows the principle:

```text
HTTP validation
       ↓
Business validation
       ↓
Database integrity
```

---

# 49. Transactions

Transactions belong in services.

The controller must not orchestrate transaction boundaries.

Example:

```text
Controller
    ↓
AppointmentService::create()
    ↓
DB::transaction()
```

This ensures the service owns the complete application operation.

---

# 50. Error Handling

Business-rule violations should be represented through dedicated application exceptions or a consistent domain/application exception mechanism.

The architecture should not scatter HTTP response generation throughout services.

Preferred flow:

```text
Service
   ↓
throws business exception
   ↓
Laravel exception handling
   ↓
structured JSON response
```

The exact HTTP status codes and JSON error contract are defined in:

```text
04-API-SPEC.md
```

---

# 51. Exceptions

The project may introduce a small set of focused exceptions when necessary.

Potential examples:

```text
AvailabilityOverlapException
AppointmentConflictException
InvalidAppointmentStateException
CancellationNotAllowedException
```

These should only be created when they improve clarity.

Do not create one exception class for every validation rule.

Simple validation failures should remain FormRequest validation errors.

---

# 52. No Repository Pattern

Repositories are explicitly out of scope.

Do not introduce:

```text
DoctorRepository
PatientRepository
AvailabilityRepository
AppointmentRepository
```

The service layer may use Eloquent directly.

This reduces unnecessary abstraction for a small coding-test project.

---

# 53. No DTO Layer

Dedicated DTO classes are not required.

Validated FormRequest data may be passed as arrays to services.

Example:

```php
$service->create($request->validated());
```

If a small immutable value object becomes necessary for a specific domain concept, it may be introduced deliberately, but this is not part of the default architecture.

---

# 54. No CQRS

The project does not use CQRS.

Reads and writes may use the same Eloquent models and service layer.

Do not create separate:

```text
Command
Query
Handler
Bus
```

structures without a concrete requirement.

---

# 55. No Event Sourcing

Event Sourcing is out of scope.

Appointment history does not require an event store.

The current appointment status is stored directly on the appointment record.

---

# 56. No Event Bus

An event-driven architecture is not required.

Laravel events/listeners should not be introduced merely for architectural style.

If a future requirement introduces an asynchronous side effect, the architecture can be extended later.

---

# 57. No Unit of Work Abstraction

Laravel's database transaction mechanism is sufficient.

Use:

```php
DB::transaction(...)
```

instead of creating a custom:

```text
UnitOfWork
```

abstraction.

---

# 58. No Full DDD Architecture

The project is not structured as a full Domain-Driven Design implementation.

Do not introduce:

```text
Aggregates
Value Objects everywhere
Domain Events
Repositories
Domain Services
Bounded Contexts
Factories for every entity
```

unless specifically required.

The existing Laravel service layer is sufficient.

---

# 59. Dependency Injection

Services should use constructor dependency injection where dependencies are stable.

Example:

```php
final class AppointmentService
{
    public function __construct(
        private readonly SlotService $slotService,
    ) {
    }
}
```

Laravel's service container should resolve dependencies.

Do not manually instantiate services inside controllers.

Avoid unnecessary service-container bindings when Laravel can resolve the class automatically.

---

# 60. Final Classes

Custom application classes should preferably be `final` when inheritance is not intended.

Examples:

```text
final class AppointmentService
final class AvailabilityService
final class SlotService
final class AppointmentController
```

The purpose is to communicate that the class is not designed as an extension point.

Framework classes and Laravel conventions take precedence.

---

# 61. Strict Types

All custom PHP files should start with:

```php
declare(strict_types=1);
```

This applies to:

* services;
* controllers;
* FormRequests;
* Resources;
* models;
* enums;
* exceptions;
* rules;
* test-support classes where applicable.

---

# 62. Type Safety

Use:

* typed properties;
* typed parameters;
* explicit return types;
* backed enums;
* nullable types where appropriate.

Avoid unnecessary:

```php
mixed
```

types.

Use precise collection/array typing where PHPStan/Larastan benefits from it.

---

# 63. Constructor Property Promotion

Constructor property promotion should be preferred where it improves readability.

Example:

```php
public function __construct(
    private readonly AppointmentService $appointmentService,
) {
}
```

Avoid verbose property declarations when promotion communicates the same information clearly.

---

# 64. Readonly

`readonly` should be used selectively.

Good candidates include immutable injected dependencies:

```php
private readonly AppointmentService $appointmentService
```

It should not be applied mechanically to Eloquent models or mutable domain state.

---

# 65. PHPStan / Larastan

Static analysis is part of the development workflow.

The project uses:

```text
PHPStan
Larastan
```

Initial target:

```text
level 5–6
```

The configuration should focus on the project's own application code.

Vendor/framework internals should not be unnecessarily analyzed.

---

# 66. Laravel Pint

Laravel Pint is the project's code formatter.

The implementation should follow Laravel/Pint formatting conventions.

Before a milestone commit:

```bash
vendor/bin/pint
```

should be run.

---

# 67. Testing Architecture

Feature tests are the primary test layer.

The main reason is that the assignment focuses on:

* REST API behavior;
* validation;
* business rules;
* database integrity;
* scheduling behavior.

Test structure:

```text
tests/
├── Feature/
│   └── Api/
│       └── V1/
│           ├── DoctorTest.php
│           ├── PatientTest.php
│           ├── AvailabilityTest.php
│           ├── AppointmentTest.php
│           └── AvailableSlotTest.php
│
└── Unit/
```

Unit tests may be added for isolated domain calculations where they provide clear value.

Feature tests remain the primary testing strategy.

---

# 68. Database Testing

Tests should use:

```php
use RefreshDatabase;
```

where database isolation is required.

Tests should verify:

* database records;
* relationships;
* validation;
* business rules;
* HTTP responses;
* pagination;
* conflict detection.

---

# 69. Time Testing

Time-dependent business rules must be deterministic in tests.

For rules involving:

* future appointments;
* future availability;
* 24-hour cancellation;

tests should freeze or explicitly control application time using Laravel's supported time-testing facilities.

Tests must not depend on the real current system clock.

---

# 70. Architecture Testing Principles

The implementation should be evaluated against the following questions:

### Controller

```text
Does it only coordinate HTTP input/output?
```

### FormRequest

```text
Does it handle basic request validation?
```

### Service

```text
Does it contain the actual business rule?
```

### Model

```text
Does it represent persistence and relationships?
```

### Resource

```text
Does it control response serialization?
```

### Database

```text
Does it protect relational integrity?
```

This responsibility model should remain consistent throughout the project.

---

# 71. Dependency Direction

The preferred dependency direction is:

```text
HTTP
 ↓
Services
 ↓
Models / Database
```

Resources serialize results from the application layer.

Models must not depend on controllers.

Services must not return HTTP responses.

FormRequests must not contain scheduling orchestration.

Resources must not perform database writes.

---

# 72. Anti-Patterns to Avoid

The implementation must avoid:

## Fat controllers

```text
Controller
 ├── validation
 ├── queries
 ├── business rules
 ├── transactions
 └── response formatting
```

## Fat models

Models should not contain the complete scheduling engine.

## Service fragmentation

Do not create dozens of tiny services such as:

```text
AppointmentConflictService
AppointmentDurationService
AppointmentFutureService
AppointmentStatusService
```

unless complexity genuinely requires them.

## Unnecessary abstractions

Avoid:

```text
Repository
DAO
DTO
CQRS
Command Bus
Query Bus
UnitOfWork
Domain Bus
```

without a concrete requirement.

---

# 73. Recommended Service Boundaries

The default boundaries are:

```text
DoctorService
    Doctor-specific orchestration

PatientService
    Patient-specific orchestration

AvailabilityService
    Availability lifecycle and overlap rules

SlotService
    Slot generation and availability calculation

AppointmentService
    Appointment lifecycle and scheduling rules
```

This is intentionally small.

---

# 74. Cross-Service Interaction

Services may collaborate where necessary.

Example:

```text
AppointmentService
       │
       └── SlotService
```

However, avoid creating circular dependencies.

Example to avoid:

```text
AppointmentService
      ↓
SlotService
      ↓
AppointmentService
```

If such a dependency appears necessary, responsibilities should be reconsidered.

---

# 75. Appointment and Slot Separation

A critical architectural distinction:

```text
SlotService
    → calculates availability

AppointmentService
    → creates and manages bookings
```

`SlotService` must not create appointments.

`AppointmentService` should not duplicate the complete slot-generation algorithm.

This prevents scheduling logic from becoming duplicated.

---

# 76. Query Ordering

All paginated collections must use deterministic ordering.

The service/query layer should explicitly define the ordering.

Example:

```php
->orderBy('start_time')
->orderBy('id')
```

The secondary `id` ordering ensures deterministic results when multiple records have the same timestamp.

The exact ordering for each endpoint is finalized in:

```text
04-API-SPEC.md
```

---

# 77. API Versioning Strategy

The current API version is:

```text
v1
```

Therefore:

```text
/api/v1/...
```

is the canonical public route.

Version-specific controllers, requests, and resources may be grouped under:

```text
Http/
├── Controllers/
│   └── Api/
│       └── V1/
├── Requests/
│   └── Api/
│       └── V1/
└── Resources/
    └── Api/
        └── V1/
```

This keeps future API versions isolated without introducing a full versioning framework.

---

# 78. API Error Boundary

Services should not know about HTTP status codes where avoidable.

For example, the service may throw:

```text
AppointmentConflictException
```

rather than:

```php
return response()->json(..., 409);
```

The HTTP layer maps the exception to the appropriate response.

This keeps business logic independent of HTTP representation.

---

# 79. Logging

Logging should be limited to useful application information.

Do not add verbose debug logging throughout the application.

Important unexpected application failures may be logged through Laravel's standard logging facilities.

Business validation failures generally do not require error-level logging.

---

# 80. Configuration

Application behavior should use Laravel configuration where appropriate.

Do not hard-code environment-specific values in services.

The canonical application timezone should be configured through Laravel's normal application configuration.

Database configuration should remain environment-driven.

---

# 81. Environment Independence

The application should not depend on:

* local filesystem paths;
* developer-specific configuration;
* hardcoded hostnames;
* hardcoded credentials;
* local machine-specific services.

The test should run from a clean Laravel installation after following the README instructions.

---

# 82. Security Boundary

Authentication and authorization are out of scope.

Nevertheless:

* validate all input;
* use Eloquent/query-builder parameterization;
* never concatenate untrusted SQL;
* do not expose unnecessary database fields;
* use API Resources for output control.

The API should not rely on frontend validation.

---

# 83. Performance Principles

The test does not require advanced performance optimization.

However:

* use indexed foreign keys;
* paginate collections;
* avoid unnecessary N+1 queries;
* eager-load relationships when the Resource requires them;
* select only necessary data when appropriate.

Do not introduce caching unless a concrete requirement appears.

---

# 84. N+1 Prevention

When a Resource accesses relationships for a collection, the service/controller layer should eager-load the required relationships.

Example:

```php
Appointment::query()
    ->with(['doctor', 'patient'])
    ->paginate();
```

Resources should not independently trigger large numbers of database queries.

---

# 85. API Resource and Query Responsibility

The query/service layer determines:

```text
which records
which relationships
which ordering
which pagination
```

The Resource determines:

```text
how those records are serialized
```

This separation must be maintained.

---

# 86. Architecture Completion Criteria

The architecture is considered correctly implemented when:

* [ ] controllers are thin;
* [ ] FormRequests handle HTTP validation;
* [ ] business rules live in services;
* [ ] Eloquent models represent persistence;
* [ ] API Resources serialize responses;
* [ ] AppointmentStatus is an enum;
* [ ] availability overlap is centralized;
* [ ] appointment conflict checks are centralized;
* [ ] slot generation is centralized;
* [ ] status transitions are centralized;
* [ ] cancellation logic is centralized;
* [ ] transactions are controlled by services;
* [ ] database integrity is enforced by migrations;
* [ ] no unnecessary Repository layer exists;
* [ ] no unnecessary DTO layer exists;
* [ ] no CQRS exists;
* [ ] no unnecessary DDD abstraction exists;
* [ ] PHPStan/Larastan is configured;
* [ ] Pint is configured;
* [ ] feature tests cover the API and business rules.

---

# 87. Final Architecture

The intended final architecture is:

```text
                         ┌────────────────────┐
                         │      Client        │
                         └─────────┬──────────┘
                                   │
                                   ▼
                         ┌────────────────────┐
                         │   /api/v1 routes   │
                         └─────────┬──────────┘
                                   │
                                   ▼
                         ┌────────────────────┐
                         │    Controllers     │
                         │      (thin)        │
                         └─────────┬──────────┘
                                   │
                    ┌──────────────┴──────────────┐
                    ▼                             ▼
             ┌──────────────┐             ┌──────────────┐
             │ FormRequests │             │   Resources  │
             │  validation  │             │ serialization│
             └──────┬───────┘             └──────▲───────┘
                    │                             │
                    ▼                             │
             ┌────────────────────────────────────┴─┐
             │               Services                 │
             │                                        │
             │ DoctorService                          │
             │ PatientService                         │
             │ AvailabilityService                    │
             │ SlotService                            │
             │ AppointmentService                     │
             └──────────────────┬─────────────────────┘
                                │
                                ▼
                         ┌─────────────────┐
                         │ Eloquent Models │
                         └────────┬────────┘
                                  │
                                  ▼
                         ┌─────────────────┐
                         │     SQLite      │
                         └─────────────────┘
```

The architecture deliberately stops here.

It is sufficient for the current backend test while remaining understandable, testable, and extensible.
