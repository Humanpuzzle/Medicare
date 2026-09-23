# Medicare Backend Test — Project Specification

## 1. Project Overview

A Laravel-based REST API for managing doctors, patients, doctor availability periods, and appointments.

The application is backend-only and exposes a JSON REST API.

The implementation should remain intentionally focused on the requirements of the test assignment. Avoid unnecessary architectural complexity or infrastructure.

---

## 2. Technical Requirements

### 2.1 Runtime

* PHP 8.3+
* Laravel 11+
* MySQL, MariaDB, or SQLite
* Composer
* PHPUnit
* Laravel Pint
* PHPStan + Larastan

### 2.2 API

* RESTful JSON API
* API routes are not versioned initially
* Base prefix:

```text
/api
```

Examples:

```text
/api/doctors
/api/patients
/api/availabilities
/api/appointments
```

No `/v1` prefix is required.

API versioning may be introduced later if the project grows beyond the scope of this test.

### 2.3 Application timezone

The application uses a single timezone:

```text
Europe/Budapest
```

All appointment and availability date/time business logic uses this timezone.

Doctor-specific timezones are out of scope.

---

## 3. Core Domain

The system contains four main entities:

* Doctor
* Patient
* Availability
* Appointment

### 3.1 Doctor

Fields:

```text
id
name
email
specialty
created_at
updated_at
```

Rules:

* `name` is required.
* `email` is required.
* `email` must be unique case-insensitively.
* `specialty` is required.
* A doctor can have multiple availability periods.
* A doctor can have multiple appointments.

---

### 3.2 Patient

Fields:

```text
id
name
email
phone
created_at
updated_at
```

Rules:

* `name` is required.
* `email` is required.
* `email` must be unique case-insensitively.
* `phone` is required.
* A patient can have multiple appointments.

---

### 3.3 Availability

Fields:

```text
id
doctor_id
starts_at
ends_at
slot_duration
created_at
updated_at
```

Rules:

* `doctor_id` must reference an existing doctor.
* `starts_at` must be before `ends_at`.
* Availability must be in the future.
* Availability duration must be at least 30 minutes.
* `slot_duration` defines the base appointment duration unit.
* Availability periods belonging to the same doctor must not overlap.
* Adjacent availability periods are allowed.

Example:

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

## 4. Appointment

Fields:

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
```

Relationships:

* Appointment belongs to a Patient.
* Appointment belongs to a Doctor.

---

## 5. Appointment Status

Appointment status is represented by an enum.

Allowed values:

```text
pending
confirmed
completed
cancelled
```

### 5.1 State transitions

Allowed transitions:

```text
pending → confirmed
pending → cancelled

confirmed → completed
confirmed → cancelled
```

The following states are terminal:

```text
completed
cancelled
```

No transition is allowed from a terminal state.

---

## 6. Cancellation Rules

### 6.1 Cancellation reason

When an appointment is cancelled, `cancellation_reason` is mandatory.

Invariant:

```text
status = cancelled
    → cancellation_reason IS NOT NULL
```

For all other statuses:

```text
status != cancelled
    → cancellation_reason IS NULL
```

Therefore:

| Status    | cancellation_reason |
| --------- | ------------------- |
| pending   | null                |
| confirmed | null                |
| completed | null                |
| cancelled | required            |

---

### 6.2 24-hour cancellation boundary

A confirmed appointment can be cancelled if its start time is **at least 24 hours in the future**.

Exactly 24 hours before the appointment is valid.

Less than 24 hours before the appointment is invalid.

Examples:

```text
Appointment: 2026-10-10 15:00

Cancellation at:
2026-10-09 15:00 → allowed
2026-10-09 15:01 → rejected
```

This rule applies only to:

```text
confirmed → cancelled
```

---

## 7. Appointment Creation Rules

Creating an appointment requires:

* patient exists,
* doctor exists,
* appointment is in the future,
* appointment is fully contained within a doctor's availability period,
* appointment duration is a valid multiple of the availability `slot_duration`,
* appointment start time follows the 15-minute grid,
* doctor has no conflicting appointment,
* patient has no conflicting appointment.

### 7.1 Future appointment

The appointment start time must be in the future relative to the application timezone:

```text
Europe/Budapest
```

---

## 8. Appointment Time Rules

### 8.1 15-minute start-time grid

Appointment start times must use a 15-minute grid.

Allowed minute values:

```text
00
15
30
45
```

Examples:

```text
09:00
09:15
09:30
09:45
10:00
```

are valid start times.

Examples:

```text
09:10
09:12
09:17
09:37
```

are invalid start times.

The purpose of this rule is to keep appointment boundaries predictable and easy to work with.

---

### 8.2 Appointment duration

Appointment duration must be an integer multiple of the availability's `slot_duration`.

For example, if:

```text
slot_duration = 30 minutes
```

then valid appointment durations include:

```text
30 minutes
60 minutes
90 minutes
120 minutes
```

Invalid:

```text
45 minutes
75 minutes
```

The appointment start time does **not** have to align with `slot_duration`.

For example, with:

```text
slot_duration = 30
```

the following are valid:

```text
09:15–09:45
09:30–10:00
10:15–11:15
```

provided the complete interval is inside the availability.

The following is invalid:

```text
09:15–10:00
```

because the duration is 45 minutes and therefore is not a multiple of 30 minutes.

The following is also invalid:

```text
09:10–09:40
```

because the start time does not follow the 15-minute grid.

---

## 9. Availability Containment

An appointment must be completely contained within a single availability period of the selected doctor.

For an availability:

```text
09:00–12:00
```

the following is valid:

```text
10:15–11:15
```

The following is invalid:

```text
08:45–09:45
```

because it starts before the availability.

The following is invalid:

```text
11:30–12:15
```

because it ends after the availability.

The appointment must not span across separate availability periods.

---

## 10. Appointment Conflict Rules

An appointment cannot overlap another appointment for the same doctor.

An appointment cannot overlap another appointment for the same patient.

Two intervals overlap when their time ranges intersect.

Adjacent appointments are allowed.

Example:

```text
09:00–09:30
09:30–10:00
```

is valid.

But:

```text
09:00–09:30
09:15–09:45
```

is invalid.

The conflict check must consider the appointment interval:

```text
[start_time, end_time)
```

so that an appointment ending at the exact time another appointment starts does not constitute an overlap.

---

## 11. Slot Generation

Availability periods can be used to generate bookable slots.

For example:

```text
Availability:
09:00–11:00

slot_duration:
30 minutes
```

generates:

```text
09:00–09:30
09:30–10:00
10:00–10:30
10:30–11:00
```

However, appointment creation does **not** require an appointment to exactly match one generated slot.

An appointment may span multiple `slot_duration` units as long as:

1. its duration is an integer multiple of `slot_duration`,
2. its start time is on the 15-minute grid,
3. its complete interval is inside the availability,
4. it does not conflict with another appointment.

Example:

```text
Availability:
09:00–12:00

slot_duration:
30

Appointment:
09:15–10:15
```

is valid.

---

## 12. Available Slots

The API should support listing available appointment slots.

The slot listing should support:

* doctor filtering,
* date/range filtering,
* pagination.

Generated slots should take existing appointments into account.

The generated slot duration is based on the availability's `slot_duration`.

The exact response structure is defined in the API specification.

---

## 13. Patient Appointments

The API should support listing appointments belonging to a patient.

Supported filtering includes appointment status.

The endpoint is paginated.

---

## 14. Pagination

The API uses Laravel's standard page-based pagination.

Default:

```text
per_page = 15
```

Maximum:

```text
per_page = 100
```

Supported parameters:

```text
page
per_page
```

Example:

```text
/api/appointments?page=2&per_page=25
```

Clients may override the default page size up to the maximum of 100.

The API should return Laravel's standard pagination metadata.

---

## 15. Email Uniqueness

Doctor and patient email addresses are unique **case-insensitively**.

These values must be treated as the same email:

```text
john@example.com
JOHN@example.com
John@Example.com
```

Case-insensitive uniqueness must not rely exclusively on application-level validation.

The database layer must also enforce the uniqueness requirement appropriately to prevent race-condition duplicates.

The exact database implementation is defined in:

```text
02-DATABASE-SPEC.md
```

---

## 16. Validation and Business Rules

Validation responsibilities are split between HTTP validation and domain/business logic.

### FormRequest

FormRequests handle:

* required fields,
* field types,
* basic formats,
* basic ranges,
* basic input constraints.

### Services

Services handle business rules such as:

* availability overlap,
* availability containment,
* appointment conflicts,
* appointment state transitions,
* 24-hour cancellation rule,
* slot-duration calculations,
* other cross-entity rules.

Business logic should not be implemented directly inside controllers.

---

## 17. Architecture

The project intentionally uses a simple Laravel architecture.

Target structure:

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
├── Services/
│   ├── AvailabilityService.php
│   ├── SlotService.php
│   └── AppointmentService.php
│
└── Rules/
```

### Responsibility boundaries

#### Controllers

Controllers are responsible for:

* receiving HTTP requests,
* invoking validation,
* calling application/domain services,
* returning API Resources / JSON responses.

Controllers should remain thin.

#### FormRequests

Responsible for HTTP/input validation.

#### Services

Responsible for business orchestration and domain rules.

#### Models

Responsible for:

* persistence,
* relationships,
* casts,
* simple model behavior.

#### Enums

Used for fixed domain values such as appointment status.

#### API Resources

Responsible for API response serialization.

---

## 18. Database Transactions

Multi-step write operations that must be atomic should use:

```php
DB::transaction(...)
```

This is especially relevant for appointment creation and other operations where multiple related database changes must succeed or fail together.

---

## 19. Coding Standards

The project follows:

* PSR-12
* Laravel coding conventions
* Laravel Pint

All custom PHP files should use:

```php
declare(strict_types=1);
```

Typed properties and parameter/return types should be used consistently.

Constructor property promotion should be used where it improves readability.

`readonly` should be used selectively for genuinely immutable objects.

Classes should preferably be `final` when there is no intended inheritance.

Avoid unnecessary abstraction.

---

## 20. Static Analysis

PHPStan with Larastan is part of the development tooling.

Initial analysis level:

```text
5–6
```

The exact level can be adjusted based on the actual Laravel codebase and test requirements.

Static analysis should be run against the project's own application code.

---

## 21. Testing

PHPUnit is used for automated testing.

Feature tests are the primary testing layer because the assignment is centered around API behavior and business rules.

Important scenarios include:

### Availability

* create valid availability,
* reject past availability,
* reject invalid start/end order,
* reject availability shorter than 30 minutes,
* reject overlapping availability for the same doctor,
* allow adjacent availability periods.

### Appointments

* create valid appointment,
* reject unknown doctor,
* reject unknown patient,
* reject past appointment,
* reject appointment outside availability,
* reject invalid 15-minute start time,
* reject duration that is not a multiple of `slot_duration`,
* allow appointment starting on a 15-minute grid but not aligned to `slot_duration`,
* reject doctor conflicts,
* reject patient conflicts,
* allow adjacent appointments.

### Status transitions

* pending → confirmed,
* pending → cancelled,
* confirmed → completed,
* confirmed → cancelled when at least 24 hours remain,
* reject confirmed → cancelled with less than 24 hours remaining,
* reject invalid transitions,
* require cancellation reason when cancelled,
* reject cancellation reason for non-cancelled status.

### Email uniqueness

* reject duplicate doctor email case-insensitively,
* reject duplicate patient email case-insensitively.

### Pagination

* default `per_page = 15`,
* custom page size,
* maximum `per_page = 100`.

The test suite should contain more than the minimum number of tests required by the assignment where practical.

---

## 22. API Resources

API responses should use Laravel API Resources rather than returning Eloquent models directly.

Resources should provide a stable JSON representation of:

* doctors,
* patients,
* availabilities,
* appointments,
* available slots.

The exact response structures are defined in:

```text
04-API-SPEC.md
```

---

## 23. Error Handling

The API should return appropriate HTTP status codes and structured JSON error responses.

Validation errors should use Laravel's standard validation response structure unless a specific API requirement dictates otherwise.

Business-rule violations should be represented consistently.

The exact error response contract is defined in:

```text
04-API-SPEC.md
```

---

## 24. Database Design

Database schema, indexes, constraints, foreign keys, timestamp handling, email uniqueness implementation, and related database-level rules are defined separately in:

```text
02-DATABASE-SPEC.md
```

The database design must support the domain invariants defined in this document.

---

## 25. Out of Scope

The following are intentionally excluded from the test implementation:

* authentication,
* authorization,
* user accounts/login,
* frontend UI,
* Blade frontend,
* Livewire,
* Inertia,
* React/Vue SPA,
* queues,
* event bus,
* microservices,
* repository pattern,
* Unit of Work abstraction,
* CQRS,
* Event Sourcing,
* full DDD framework,
* unnecessary design-pattern abstractions,
* Laravel Boost.

The implementation should remain focused on the requirements of the backend test.

---

## 26. Project Documentation

The repository should contain a README explaining at minimum:

* project purpose,
* requirements,
* installation,
* environment setup,
* database setup,
* migrations,
* how to run the application,
* how to run tests,
* how to run static analysis,
* how to run code formatting,
* available API endpoints,
* relevant design decisions.

No `.env` file or `vendor/` directory should be committed.

---

## 27. Implementation Scope

The implementation should prioritize:

1. Correct domain behavior.
2. Clear API design.
3. Proper validation.
4. Reliable business-rule enforcement.
5. Database integrity.
6. Automated tests.
7. Static analysis.
8. Clean, maintainable Laravel code.

Avoid premature abstraction or infrastructure that is not required by the assignment.

The target is a focused implementation suitable for a backend coding test rather than a production-scale enterprise architecture.

---

# 28. Finalized Open Decisions

All previously open project decisions have been resolved.

### DEC-001 — Appointment Start / Slot

An appointment must start at a valid generated booking time.

A slot represents a bookable appointment start time, not necessarily a fixed-duration appointment block.

### DEC-002 — Appointment Duration and Time Grid

- Every appointment must be at least 30 minutes long.
- Appointment start times must use a 15-minute grid.
- Valid start minutes are `00`, `15`, `30`, and `45`.
- An appointment must fit completely inside the doctor's active availability.
- An appointment must not extend beyond the availability end.
- Appointment duration is not globally fixed to 30 minutes.

### DEC-003 — Cancellation Reason

`cancellation_reason` is optional and nullable.

### DEC-004 — Cancellation Boundary

An appointment can be cancelled when at least 24 hours remain until its start time.

Boundary:

`>= 24 hours` → allowed  
`< 24 hours` → rejected

### DEC-005 — Timezone

The application uses UTC for internal date/time handling.

### DEC-006 — Email Case Sensitivity

Email addresses are handled case-insensitively and normalized to lowercase before persistence.

### DEC-007 — Pagination

Collection endpoints are paginated.

- Default `per_page`: `25`
- Maximum `per_page`: `100`
- Backend controls deterministic ordering.

### DEC-008 — API Versioning

All API endpoints are versioned under:

`/api/v1`                                                               |

There are no remaining Open Decisions in the current project specification.

---

# 29. Implementation Principles

The implementation should follow these principles:

### Keep controllers thin

```text
HTTP request
    ↓
FormRequest
    ↓
Service
    ↓
Model / Database
    ↓
API Resource
    ↓
JSON response
```

### Keep business rules centralized

Rules involving multiple entities or state transitions belong in services/domain logic rather than controllers.

### Prefer framework conventions

Use Laravel's existing mechanisms where they provide a clear solution.

Avoid introducing custom abstractions when Laravel already provides an appropriate mechanism.

### Enforce important invariants at multiple levels

Where appropriate:

```text
HTTP validation
      ↓
Business validation
      ↓
Database constraints
```

Application-level validation alone should not be relied upon for critical integrity constraints such as uniqueness.

### Optimize for clarity

The code should be easy for another developer to understand during a code review.

Complexity should only be introduced when the domain requirement actually requires it.
