# Medicare Backend Test — Database Specification

## 1. Purpose

This document defines the database schema, relationships, constraints, indexes, data types, timestamp handling, soft deletes, and database-level integrity rules for the Medicare Backend Test.

The database design must support the domain rules defined in:

```text
01-PROJECT-SPEC.md
```

The implementation should remain intentionally simple and focused on the requirements of the coding test.

---

# 2. Database Engine

The application must support one of the following relational database engines:

* MySQL
* MariaDB
* SQLite

The development/test environment will use:

```text
SQLite
```

The schema should avoid database-specific functionality unless it is required to enforce an important invariant.

Laravel migrations must remain compatible with SQLite.

---

# 3. General Database Conventions

## 3.1 Primary keys

All entities use:

```text
id
```

with an auto-incrementing integer primary key.

Laravel's default:

```php
$table->id();
```

should be used.

---

## 3.2 Timestamps

All main domain tables contain:

```text
created_at
updated_at
```

Laravel's standard:

```php
$table->timestamps();
```

should be used.

---

## 3.3 Soft deletes

The following entities use soft deletes:

* Doctor
* Patient
* Availability
* Appointment

Each table therefore contains:

```text
deleted_at
```

implemented through Laravel's:

```php
$table->softDeletes();
```

Models must use:

```php
use SoftDeletes;
```

Soft-deleted records must not normally appear in application queries.

---

# 4. Timezone and Date/Time Storage

## 4.1 Application timezone

The application timezone is:

```text
Europe/Budapest
```

This is the timezone used for domain-level interpretation of appointment and availability times.

---

## 4.2 Database storage

Date/time values must be stored in a consistent UTC representation.

The application is responsible for converting incoming `Europe/Budapest` date/time values to UTC before persistence and converting persisted UTC values back when producing API responses.

The database must not contain a mixture of local-time and UTC values.

---

## 4.3 Date/time columns

The following fields represent timezone-aware domain timestamps:

```text
availabilities.starts_at
availabilities.ends_at

appointments.start_time
appointments.end_time
```

Laravel date casting should be used on these attributes.

The exact Eloquent cast implementation belongs to the model layer.

---

# 5. Tables

The database contains four primary domain tables:

```text
doctors
patients
availabilities
appointments
```

Relationship overview:

```text
Doctor
 ├── hasMany Availability
 └── hasMany Appointment

Patient
 └── hasMany Appointment

Availability
 └── belongsTo Doctor

Appointment
 ├── belongsTo Doctor
 └── belongsTo Patient
```

---

# 6. Doctors Table

## 6.1 Schema

Table:

```text
doctors
```

Columns:

| Column     | Type            | Nullable | Description           |
| ---------- | --------------- | -------: | --------------------- |
| id         | BIGINT UNSIGNED |       No | Primary key           |
| name       | VARCHAR         |       No | Doctor name           |
| email      | VARCHAR         |       No | Unique email          |
| specialty  | VARCHAR         |       No | Medical specialty     |
| created_at | TIMESTAMP       |      Yes | Creation timestamp    |
| updated_at | TIMESTAMP       |      Yes | Last update timestamp |
| deleted_at | TIMESTAMP       |      Yes | Soft delete timestamp |

---

## 6.2 Constraints

### Primary key

```text
PRIMARY KEY (id)
```

### Required fields

The following columns are NOT NULL:

```text
name
email
specialty
```

### Email uniqueness

Doctor email addresses must be unique case-insensitively.

The database design must prevent:

```text
john@example.com
JOHN@example.com
John@Example.com
```

from existing simultaneously as active doctor records.

---

## 6.3 Email normalization

The application normalizes doctor email addresses to lowercase before persistence.

Example:

```text
John@Example.com
```

becomes:

```text
john@example.com
```

Database uniqueness is therefore enforced against the normalized value.

A database-level unique constraint/index must also exist.

This prevents race-condition duplicates where application-level validation alone would not be sufficient.

---

## 6.4 Soft delete and email reuse

Soft-deleted doctors should not block creation of a new active doctor using the same email address.

Therefore the uniqueness strategy must account for:

```text
deleted_at IS NULL
```

The exact implementation must remain compatible with SQLite.

For SQLite, a partial unique index may be used:

```sql
CREATE UNIQUE INDEX ...
ON doctors(email)
WHERE deleted_at IS NULL;
```

If the migration implementation needs to use raw SQL for SQLite compatibility, this should be documented directly in the migration.

---

# 7. Patients Table

## 7.1 Schema

Table:

```text
patients
```

Columns:

| Column     | Type            | Nullable | Description           |
| ---------- | --------------- | -------: | --------------------- |
| id         | BIGINT UNSIGNED |       No | Primary key           |
| name       | VARCHAR         |       No | Patient name          |
| email      | VARCHAR         |       No | Unique email          |
| phone      | VARCHAR         |       No | Patient phone number  |
| created_at | TIMESTAMP       |      Yes | Creation timestamp    |
| updated_at | TIMESTAMP       |      Yes | Last update timestamp |
| deleted_at | TIMESTAMP       |      Yes | Soft delete timestamp |

---

## 7.2 Constraints

### Primary key

```text
PRIMARY KEY (id)
```

### Required fields

The following columns are NOT NULL:

```text
name
email
phone
```

---

## 7.3 Email uniqueness

Patient emails follow the same rules as doctor emails.

Email comparison is case-insensitive.

Application-level normalization:

```text
lowercase(email)
```

Database-level uniqueness must also be enforced.

Active patient records must not contain duplicate normalized emails.

---

## 7.4 Soft delete and email reuse

Soft-deleted patients should not block reuse of their email address.

The uniqueness constraint should therefore apply only to active records:

```text
deleted_at IS NULL
```

For SQLite, a partial unique index is acceptable.

---

# 8. Availabilities Table

## 8.1 Schema

Table:

```text
availabilities
```

Columns:

| Column        | Type               | Nullable | Description                   |
| ------------- | ------------------ | -------: | ----------------------------- |
| id            | BIGINT UNSIGNED    |       No | Primary key                   |
| doctor_id     | BIGINT UNSIGNED    |       No | Related doctor                |
| starts_at     | DATETIME/TIMESTAMP |       No | Availability start            |
| ends_at       | DATETIME/TIMESTAMP |       No | Availability end              |
| slot_duration | UNSIGNED INTEGER   |       No | Base slot duration in minutes |
| created_at    | TIMESTAMP          |      Yes | Creation timestamp            |
| updated_at    | TIMESTAMP          |      Yes | Last update timestamp         |
| deleted_at    | TIMESTAMP          |      Yes | Soft delete timestamp         |

---

## 8.2 Doctor relationship

```text
availabilities.doctor_id
    →
doctors.id
```

Foreign key:

```text
FOREIGN KEY (doctor_id)
REFERENCES doctors(id)
```

The relationship is mandatory.

---

## 8.3 Availability interval

The following invariant must hold:

```text
starts_at < ends_at
```

An availability with equal start and end times is invalid.

Example:

```text
09:00 < 10:00
```

valid.

```text
09:00 = 09:00
```

invalid.

---

## 8.4 Minimum duration

Every availability must be at least 30 minutes long.

Therefore:

```text
ends_at - starts_at >= 30 minutes
```

This is primarily enforced by application/business validation.

Because this is a cross-value temporal constraint, it should not rely exclusively on a generic database column constraint.

---

## 8.5 Future availability

Availability must be in the future according to the application timezone:

```text
Europe/Budapest
```

This is a business rule and must be enforced in application/service logic.

A database migration should not attempt to encode a dynamic `NOW()` constraint.

---

## 8.6 Slot duration

`slot_duration` represents the base appointment-duration unit used when generating available slots.

Example:

```text
slot_duration = 30
```

means generated slots are 30 minutes long.

Recommended validation:

```text
slot_duration >= 30
```

`slot_duration` must be a positive integer representing minutes.

The API/business validation should reject invalid values.

The database stores the value as an unsigned integer.

---

## 8.7 Availability overlap

Availability periods belonging to the same doctor must not overlap.

Intervals use half-open semantics:

```text
[start, end)
```

Therefore:

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

The overlap rule is:

```text
existing.starts_at < new.ends_at
AND
existing.ends_at > new.starts_at
```

Only active availability records should participate in the conflict check.

Soft-deleted availability records do not block creation of a new availability.

---

## 8.8 Availability overlap enforcement

The overlap rule is a business/domain constraint.

It should be enforced in:

```text
AvailabilityService
```

The database does not need a complex exclusion constraint because:

* SQLite does not provide PostgreSQL-style exclusion constraints.
* MySQL/MariaDB implementations would require a different mechanism.
* The project explicitly favors portability and simplicity.

The service must perform the overlap check inside an appropriate transaction when necessary.

---

## 8.9 Availability indexes

Required index:

```text
INDEX(doctor_id)
```

Recommended composite index:

```text
INDEX(doctor_id, starts_at, ends_at)
```

This supports:

* doctor availability lookup,
* overlap queries,
* appointment containment checks.

---

# 9. Appointments Table

## 9.1 Schema

Table:

```text
appointments
```

Columns:

| Column              | Type               | Nullable | Description           |
| ------------------- | ------------------ | -------: | --------------------- |
| id                  | BIGINT UNSIGNED    |       No | Primary key           |
| patient_id          | BIGINT UNSIGNED    |       No | Related patient       |
| doctor_id           | BIGINT UNSIGNED    |       No | Related doctor        |
| start_time          | DATETIME/TIMESTAMP |       No | Appointment start     |
| end_time            | DATETIME/TIMESTAMP |       No | Appointment end       |
| status              | VARCHAR/ENUM       |       No | Appointment status    |
| cancellation_reason | TEXT               |      Yes | Cancellation reason   |
| created_at          | TIMESTAMP          |      Yes | Creation timestamp    |
| updated_at          | TIMESTAMP          |      Yes | Last update timestamp |
| deleted_at          | TIMESTAMP          |      Yes | Soft delete timestamp |

---

# 10. Appointment Foreign Keys

## 10.1 Patient

```text
appointments.patient_id
    →
patients.id
```

Foreign key:

```text
FOREIGN KEY (patient_id)
REFERENCES patients(id)
```

---

## 10.2 Doctor

```text
appointments.doctor_id
    →
doctors.id
```

Foreign key:

```text
FOREIGN KEY (doctor_id)
REFERENCES doctors(id)
```

Both relationships are mandatory.

---

# 11. Foreign Key Delete Behavior

The preferred behavior is:

```text
ON DELETE RESTRICT
```

or the database-equivalent behavior that prevents deleting a referenced doctor/patient while dependent active records exist.

Because the application uses soft deletes, normal domain deletion should occur through Laravel's SoftDeletes rather than physical deletion.

This prevents accidental cascade deletion of historical appointments.

No cascading delete should be configured from:

```text
doctors → appointments
patients → appointments
doctors → availabilities
```

---

# 12. Appointment Status

The appointment status is represented in application code by:

```text
AppointmentStatus
```

Allowed values:

```text
pending
confirmed
completed
cancelled
```

The database column stores the corresponding string value.

Using a string column rather than a database-specific ENUM keeps the SQLite/MySQL/MariaDB schema portable.

The Laravel model should cast the field to:

```php
AppointmentStatus::class
```

---

# 13. Cancellation Reason

`cancellation_reason` is nullable.

Required invariant:

```text
status = cancelled
    → cancellation_reason IS NOT NULL
```

For all other statuses:

```text
status != cancelled
    → cancellation_reason IS NULL
```

This rule is primarily enforced by the application/domain layer.

The database column remains nullable because:

```text
pending
confirmed
completed
```

must be able to store:

```text
NULL
```

---

# 14. Appointment Interval

Appointments use half-open interval semantics:

```text
[start_time, end_time)
```

This means:

```text
09:00–09:30
09:30–10:00
```

do not overlap.

But:

```text
09:00–09:30
09:15–09:45
```

do overlap.

---

# 15. Appointment Start/End Integrity

The following invariant must hold:

```text
start_time < end_time
```

The database should store both values as NOT NULL.

Application/business validation must additionally enforce:

```text
duration >= 30 minutes
```

---

# 16. Appointment Duration

Appointment duration is calculated from:

```text
end_time - start_time
```

The duration must:

1. be at least 30 minutes;
2. be an integer multiple of the selected availability's `slot_duration`.

Example:

```text
slot_duration = 30

30 minutes  → valid
60 minutes  → valid
90 minutes  → valid
120 minutes → valid
```

But:

```text
45 minutes → invalid
75 minutes → invalid
```

The appointment does not have to start on the `slot_duration` boundary.

It only needs to start on the global 15-minute grid.

Example:

```text
slot_duration = 30

09:15–09:45
```

is valid.

---

# 17. Appointment 15-Minute Grid

The database stores the actual timestamp.

The 15-minute grid is a business validation rule.

Valid minute components:

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
```

Invalid:

```text
09:10
09:12
09:17
09:37
```

This should be enforced by `AppointmentService` / validation logic rather than relying on database-specific CHECK expressions.

---

# 18. Appointment Availability Containment

An appointment must be fully contained inside one active availability belonging to the same doctor.

For:

```text
Availability:
09:00–12:00
```

this is valid:

```text
10:15–11:15
```

These are invalid:

```text
08:45–09:45
11:30–12:15
```

The appointment must not span across separate availability records.

The database should support efficient lookup through the availability indexes.

---

# 19. Appointment Conflict Detection

An appointment cannot conflict with another active appointment belonging to:

* the same doctor;
* the same patient.

Conflict detection uses:

```text
existing.start_time < new.end_time
AND
existing.end_time > new.start_time
```

This implements:

```text
[start_time, end_time)
```

interval semantics.

---

# 20. Appointment Conflict Indexes

The appointments table should contain indexes supporting conflict and lookup queries.

Required indexes:

```text
INDEX(doctor_id)
INDEX(patient_id)
```

Recommended composite indexes:

```text
INDEX(doctor_id, start_time, end_time)
INDEX(patient_id, start_time, end_time)
```

These indexes support:

* doctor appointment lists,
* patient appointment lists,
* doctor conflict checks,
* patient conflict checks,
* date/range filtering.

---

# 21. Soft Deletes and Appointment Conflicts

Soft-deleted appointments must not participate in active conflict detection.

For example:

```text
09:00–10:00 cancelled/deleted
09:00–10:00 new appointment
```

The new appointment is not blocked by the soft-deleted record.

Application queries must therefore use Laravel's default SoftDeletes behavior.

When historical/deleted records are explicitly required, `withTrashed()` may be used.

---

# 22. Status and Conflict Semantics

Conflict detection applies to active appointments.

A cancelled appointment must not block a new appointment.

A completed appointment remains a historical appointment and should normally not be considered a future scheduling conflict.

The service should therefore consider only appointment states that represent an active booking when performing future conflict checks.

For the current domain:

```text
pending
confirmed
```

are active scheduling states.

```text
completed
cancelled
```

are not available for future conflict blocking.

---

# 23. Email Uniqueness Strategy

Both:

```text
doctors.email
patients.email
```

must be unique case-insensitively.

The chosen strategy is:

1. normalize email to lowercase in the application;
2. store normalized lowercase value;
3. create a database-level unique index;
4. ensure soft-deleted records do not block reuse.

For SQLite, the preferred implementation is a partial unique index:

```sql
CREATE UNIQUE INDEX doctors_email_unique_active
ON doctors(email)
WHERE deleted_at IS NULL;
```

and:

```sql
CREATE UNIQUE INDEX patients_email_unique_active
ON patients(email)
WHERE deleted_at IS NULL;
```

This provides database-level protection against duplicate active emails.

---

# 24. Soft Delete Uniqueness Considerations

Soft deletion creates an important distinction:

```text
active record
deleted record
```

For uniqueness-sensitive fields such as email, only active records participate in uniqueness.

Example:

```text
Doctor #1
email = john@example.com
deleted_at = NULL
```

prevents another active doctor from using:

```text
john@example.com
```

After Doctor #1 is soft deleted:

```text
deleted_at != NULL
```

a new active doctor may use:

```text
john@example.com
```

---

# 25. Database-Level vs Application-Level Rules

Not every business rule should be implemented as a database constraint.

## Database-level constraints

The database should enforce:

* primary keys;
* foreign keys;
* NOT NULL requirements;
* active email uniqueness;
* basic relational integrity.

## Application/service-level rules

The application should enforce:

* availability is in the future;
* availability duration >= 30 minutes;
* availability start < end;
* availability overlap;
* appointment is in the future;
* appointment duration >= 30 minutes;
* appointment duration is a multiple of `slot_duration`;
* appointment starts on the 15-minute grid;
* appointment containment;
* appointment conflicts;
* status transitions;
* cancellation rules;
* cancellation reason invariant.

This separation avoids database-specific business logic while maintaining strong relational integrity.

---

# 26. Recommended Migration Order

Migrations should be created in dependency order.

Recommended sequence:

```text
1. create_doctors_table
2. create_patients_table
3. create_availabilities_table
4. create_appointments_table
5. add/define active email unique indexes
```

The actual Laravel migration timestamps determine execution order.

---

# 27. Migration Requirements

Migrations must be:

* reversible;
* deterministic;
* compatible with SQLite;
* safe to run from a clean database.

Each migration should implement:

```php
public function up(): void
```

and:

```php
public function down(): void
```

No manual database setup should be required after running:

```bash
php artisan migrate
```

---

# 28. Suggested Laravel Migration Structure

## Doctors

Conceptually:

```php
Schema::create('doctors', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email');
    $table->string('specialty');
    $table->timestamps();
    $table->softDeletes();
});
```

The active email unique index is added separately where necessary for SQLite partial-index support.

---

## Patients

```php
Schema::create('patients', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email');
    $table->string('phone');
    $table->timestamps();
    $table->softDeletes();
});
```

Again, active email uniqueness is enforced through the appropriate database index.

---

## Availabilities

```php
Schema::create('availabilities', function (Blueprint $table) {
    $table->id();
    $table->foreignId('doctor_id')
        ->constrained()
        ->restrictOnDelete();

    $table->dateTime('starts_at');
    $table->dateTime('ends_at');
    $table->unsignedInteger('slot_duration');

    $table->index([
        'doctor_id',
        'starts_at',
        'ends_at',
    ]);

    $table->timestamps();
    $table->softDeletes();
});
```

---

## Appointments

```php
Schema::create('appointments', function (Blueprint $table) {
    $table->id();

    $table->foreignId('patient_id')
        ->constrained()
        ->restrictOnDelete();

    $table->foreignId('doctor_id')
        ->constrained()
        ->restrictOnDelete();

    $table->dateTime('start_time');
    $table->dateTime('end_time');

    $table->string('status');
    $table->text('cancellation_reason')->nullable();

    $table->index([
        'doctor_id',
        'start_time',
        'end_time',
    ]);

    $table->index([
        'patient_id',
        'start_time',
        'end_time',
    ]);

    $table->timestamps();
    $table->softDeletes();
});
```

The final migration implementation may adjust index definitions slightly where Laravel/SQLite compatibility requires it.

---

# 29. Eloquent Model Requirements

All four domain models should use:

```text
SoftDeletes
```

Models:

```text
Doctor
Patient
Availability
Appointment
```

Relationships should be defined using standard Eloquent relationships.

---

## 29.1 Doctor

```text
Doctor
 ├── hasMany(Availability)
 └── hasMany(Appointment)
```

---

## 29.2 Patient

```text
Patient
 └── hasMany(Appointment)
```

---

## 29.3 Availability

```text
Availability
 └── belongsTo(Doctor)
```

---

## 29.4 Appointment

```text
Appointment
 ├── belongsTo(Doctor)
 └── belongsTo(Patient)
```

---

# 30. Model Casting

Recommended casts:

## Availability

```text
starts_at → datetime
ends_at   → datetime
```

## Appointment

```text
start_time → datetime
end_time   → datetime
status     → AppointmentStatus enum
```

The enum cast should use the project's:

```text
App\Enums\AppointmentStatus
```

---

# 31. Database Integrity Invariants

The following invariants must hold across the database/application boundary.

## Doctor

```text
name IS NOT NULL
email IS NOT NULL
specialty IS NOT NULL
active email is unique
```

## Patient

```text
name IS NOT NULL
email IS NOT NULL
phone IS NOT NULL
active email is unique
```

## Availability

```text
doctor_id references an existing doctor
starts_at < ends_at
duration >= 30 minutes
slot_duration is a positive integer
same-doctor active availability periods do not overlap
```

## Appointment

```text
doctor_id references an existing doctor
patient_id references an existing patient
start_time < end_time
duration >= 30 minutes
duration % slot_duration = 0
start time uses 15-minute grid
appointment is contained within one availability
doctor has no active conflicting appointment
patient has no active conflicting appointment
```

## Cancellation

```text
status = cancelled
    → cancellation_reason IS NOT NULL

status != cancelled
    → cancellation_reason IS NULL
```

---

# 32. Data Lifecycle

The normal lifecycle uses soft deletion.

Example:

```text
Active
  ↓
Soft deleted
```

The record remains in the database but is excluded from normal Eloquent queries.

Historical data should therefore remain available when explicitly required.

Physical deletion is not part of the normal application workflow.

---

# 33. Querying Deleted Records

Normal application queries should use Laravel's default SoftDeletes behavior.

For explicit historical/administrative access:

```php
Model::withTrashed()
```

may be used.

Permanent deletion:

```php
Model::forceDelete()
```

is outside the normal API workflow.

---

# 34. Database Transactions

Multi-step operations that modify related state must use database transactions.

Appointment creation should be treated as an atomic operation where appropriate:

```text
begin transaction
    ↓
validate relevant state
    ↓
check availability
    ↓
check doctor conflict
    ↓
check patient conflict
    ↓
create appointment
    ↓
commit
```

If the operation fails:

```text
rollback
```

The exact transaction boundary belongs to the service implementation.

---

# 35. Concurrency Considerations

Application-level validation alone cannot guarantee uniqueness or conflict prevention under concurrent requests.

For email uniqueness, the database unique index is authoritative.

For appointment conflicts, the service must perform conflict checks within an appropriate transaction.

The test implementation does not require a sophisticated distributed locking mechanism.

Avoid introducing unnecessary infrastructure such as:

* Redis locks;
* queue-based scheduling;
* distributed transactions;
* external locking services.

The goal is to maintain correctness without unnecessary architecture.

---

# 36. Seed Data Requirements

The database should provide seed data sufficient to demonstrate:

* multiple doctors;
* multiple patients;
* multiple availability periods;
* different `slot_duration` values;
* multiple appointments;
* different appointment statuses.

Seed data should respect all domain invariants.

Examples should include:

* adjacent availability periods;
* appointments spanning multiple slot-duration units;
* appointments beginning on a 15-minute grid but not necessarily on a slot-duration boundary.

Seed data must not contain:

* overlapping availability for the same doctor;
* conflicting active appointments;
* invalid appointment durations;
* cancelled appointments without cancellation reasons.

---

# 37. Factory Requirements

Factories should be provided for:

```text
DoctorFactory
PatientFactory
AvailabilityFactory
AppointmentFactory
```

Factories should generate valid default records.

Invalid edge cases should be generated explicitly in tests rather than making the default factory produce invalid data.

Factories should support states where useful, for example:

```text
AppointmentFactory::new()->confirmed()
AppointmentFactory::new()->cancelled()
```

The exact factory states belong to the implementation phase.

---

# 38. Testing Database

Automated tests should use the project's SQLite test database.

The preferred approach is Laravel's standard:

```text
RefreshDatabase
```

testing strategy.

Tests must run against a clean, deterministic schema.

Database tests must verify both:

* application-level behavior;
* database-level integrity where applicable.

Email uniqueness tests are particularly important because uniqueness is intentionally enforced at the database level.

---

# 39. Database Design Summary

The final schema is intentionally small:

```text
┌──────────────┐
│   doctors    │
├──────────────┤
│ id           │
│ name         │
│ email        │
│ specialty    │
│ timestamps   │
│ deleted_at   │
└──────┬───────┘
       │
       ├──────────────────────┐
       │                      │
       │ 1:N                  │ 1:N
       ▼                      ▼
┌─────────────────┐    ┌──────────────────┐
│ availabilities  │    │   appointments   │
├─────────────────┤    ├──────────────────┤
│ id              │    │ id               │
│ doctor_id       │    │ doctor_id        │
│ starts_at       │    │ patient_id       │
│ ends_at         │    │ start_time       │
│ slot_duration   │    │ end_time         │
│ timestamps      │    │ status           │
│ deleted_at      │    │ cancellation...  │
└─────────────────┘    │ timestamps       │
                       │ deleted_at       │
                       └────────┬─────────┘
                                │
                                │ N:1
                                ▼
                       ┌─────────────────┐
                       │    patients     │
                       ├─────────────────┤
                       │ id              │
                       │ name            │
                       │ email           │
                       │ phone           │
                       │ timestamps      │
                       │ deleted_at      │
                       └─────────────────┘
```

The database layer remains intentionally simple while providing the relational integrity required by the assignment.

---

# 40. Implementation Checklist

Before considering the database layer complete, verify:

* [ ] SQLite configured
* [ ] `doctors` migration created
* [ ] `patients` migration created
* [ ] `availabilities` migration created
* [ ] `appointments` migration created
* [ ] foreign keys configured
* [ ] soft deletes configured
* [ ] timestamps configured
* [ ] active doctor email uniqueness enforced
* [ ] active patient email uniqueness enforced
* [ ] availability lookup indexes created
* [ ] appointment doctor indexes created
* [ ] appointment patient indexes created
* [ ] AppointmentStatus enum mapped
* [ ] datetime casts defined
* [ ] factories created
* [ ] seed data created
* [ ] migrations pass on SQLite
* [ ] rollback works
* [ ] database tests pass
* [ ] conflict queries verified
* [ ] soft-deleted records excluded from normal scheduling queries

---

# 41. Relationship to Other Specifications

This document defines the persistence layer.

The following responsibilities remain outside this document:

```text
01-PROJECT-SPEC.md
    → project-wide requirements and business rules

03-ARCHITECTURE.md
    → application structure and responsibility boundaries

04-API-SPEC.md
    → endpoints, request/response schemas and HTTP errors

05-IMPLEMENTATION-SPEC.md
    → concrete implementation sequence and coding tasks

06-SETUP-AND-WORKFLOW.md
    → environment setup, development workflow and Git process
```

No business rule defined in this document should contradict the finalized `01-PROJECT-SPEC.md`.
