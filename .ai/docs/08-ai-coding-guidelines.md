# 08 — AI Coding Guidelines

## 1. Purpose

This document defines the rules for implementing the backend test project with the assistance of an AI coding agent.

The AI agent must treat the existing project specification documents as the source of truth.

The implementation must follow the specifications in this order:

1. `01-PROJECT-SPEC.md`
2. `02-DATABASE-SPEC.md`
3. `03-ARCHITECTURE.md`
4. `04-API-SPEC.md`
5. `05-IMPLEMENTATION-SPEC.md`
6. `06-SETUP-AND-WORKFLOW.md`
7. `07-TESTING-SPEC.md`
8. `08-AI-CODING-GUIDELINES.md`

If two requirements appear to conflict, do not silently choose one. Stop and identify the conflict before implementing the affected part.

---

# 2. General AI Coding Principles

The AI agent must:

* make small, isolated changes;
* prefer simple Laravel-native solutions;
* follow the existing architecture;
* avoid unnecessary abstractions;
* avoid speculative features;
* avoid changing unrelated files;
* write tests together with implementation;
* run the relevant test suite after each feature;
* run static analysis regularly;
* run Laravel Pint before milestone completion;
* preserve existing behavior unless the specification explicitly requires a change.

The AI agent must not implement functionality merely because it is common in Laravel projects.

Every implementation decision must have a reason based on:

* project specification;
* API specification;
* database specification;
* testing specification;
* explicit user instruction.

---

# 3. Source of Truth

The specification documents are authoritative.

The AI agent must not replace project-specific decisions with personal framework preferences.

For example:

* SQLite must remain SQLite.
* Soft deletes must remain enabled.
* API versioning must remain `/api/v1`.
* Appointment times must use 15-minute boundaries.
* Controllers must remain thin.
* Business logic must not be moved into controllers for convenience.
* PHPUnit must remain the test framework.
* PHPStan/Larastan must be used.
* Laravel Pint must be used.
* No frontend should be introduced.
* No Docker setup should be introduced unless explicitly requested.
* No external database should be introduced.

---

# 4. Before Starting Implementation

Before changing code, the AI agent must inspect the current project state.

Minimum inspection:

```bash
git status
php -v
composer --version
php artisan --version
php artisan about
```

Then inspect:

* `composer.json`
* `.env`
* `routes/api.php`
* `app/Models`
* `app/Http`
* `database/migrations`
* `database/factories`
* `database/seeders`
* `tests`

The agent must understand the current state before generating new files.

Do not recreate files that already exist without checking their contents.

---

# 5. Implementation Order

Implementation must follow the feature dependency order.

Recommended sequence:

1. Laravel project setup
2. SQLite configuration
3. base migrations
4. enums
5. models and relationships
6. factories
7. seeders
8. API route structure
9. Doctors
10. Patients
11. Availability
12. Appointment creation
13. Appointment update/status transitions
14. Appointment cancellation
15. Slot generation
16. Appointment listings
17. API Resources
18. validation/error handling refinement
19. tests and edge cases
20. static analysis and formatting
21. documentation
22. final verification

Do not implement appointment logic before the required doctor, patient, and availability infrastructure exists.

---

# 6. Work in Small Milestones

Each implementation milestone should be independently understandable.

A milestone should preferably contain:

* implementation;
* tests;
* relevant documentation changes;
* verification.

Example:

```text
Milestone:
Doctor CRUD

Implementation:
- Doctor model
- migration
- factory
- controller
- requests
- resource
- routes

Tests:
- create
- list
- show
- update
- delete
- validation
- not found
```

The agent should not implement the entire application in one uncontrolled operation.

---

# 7. One Feature at a Time

For each feature:

### Step 1 — Understand

Identify:

* required endpoint(s);
* HTTP method;
* request payload;
* validation rules;
* database changes;
* business rules;
* response structure;
* error conditions;
* required tests.

### Step 2 — Implement

Implement only the required functionality.

### Step 3 — Test

Run the feature-specific tests.

### Step 4 — Review

Check:

* architecture;
* naming;
* typing;
* validation;
* error handling;
* transactions;
* soft delete behavior;
* authorization-independent business rules.

### Step 5 — Quality Gate

Run:

```bash
php artisan test
vendor/bin/phpstan analyse
vendor/bin/pint --test
```

Fix failures before moving to the next milestone.

---

# 8. Controllers

Controllers must remain thin.

Controllers should primarily:

1. receive the request;
2. validate through FormRequest;
3. call the appropriate application/business action;
4. return an API Resource or appropriate response.

Avoid:

```php
public function store(Request $request)
{
    // large business logic
}
```

Prefer:

```text
Controller
    ↓
FormRequest
    ↓
Action / Service
    ↓
Model
    ↓
Resource
```

Controllers must not contain complex:

* availability calculations;
* slot generation;
* conflict detection;
* status transition logic;
* cancellation rules;
* transaction workflows.

---

# 9. Business Logic

Business rules must have a clear home outside controllers.

Examples include:

* availability overlap detection;
* appointment availability validation;
* doctor conflict detection;
* patient conflict detection;
* appointment status transitions;
* cancellation rules;
* slot generation.

The implementation should favor cohesive Actions or Services.

Do not create an abstraction only to move one line of code.

---

# 10. Validation

Input validation must be handled through FormRequest classes where appropriate.

Validation errors must produce:

```http
422 Unprocessable Entity
```

Validation should cover both:

* syntactic input requirements;
* basic domain constraints that can be represented as validation rules.

Business conflicts that require database/domain evaluation must not be incorrectly represented as ordinary validation errors.

For example:

```text
Invalid datetime format
    → 422

Invalid 15-minute boundary
    → 422

Appointment conflicts with another appointment
    → 409
```

---

# 11. HTTP Error Semantics

The API must maintain the documented status code contract.

| Situation                     | HTTP status |
| ----------------------------- | ----------: |
| Successful read/create/update |   200 / 201 |
| Successful delete             |         204 |
| Validation failure            |         422 |
| Resource not found            |         404 |
| Business conflict             |         409 |
| Invalid state transition      |         409 |
| Unexpected server error       |         500 |

Error responses must use the documented JSON structure.

Internal exception details and stack traces must never be exposed through the API.

---

# 12. Transactions

Use database transactions for multi-step write operations where partial completion would produce an invalid state.

Typical examples:

* creating an appointment;
* updating appointment state when multiple records/actions are involved;
* operations involving multiple dependent writes.

Use:

```php
DB::transaction(function () {
    // atomic operation
});
```

Do not wrap every database query in a transaction without a reason.

The transaction boundary should represent a meaningful business operation.

---

# 13. Soft Deletes

Soft-deleted records must not participate in active business logic.

This applies to:

* doctors;
* patients;
* availabilities;
* appointments.

The AI agent must explicitly consider soft-delete behavior whenever querying these entities.

Examples:

* deleted availability must not generate slots;
* deleted appointment must not block a new appointment;
* deleted doctor must not be treated as available for new appointments;
* deleted patient must not be treated as an active patient.

Do not bypass Laravel's soft-delete behavior unintentionally.

---

# 14. Date and Time Rules

Appointment and availability time logic must follow the project specification.

All appointment times must use a 15-minute grid:

```text
00
15
30
45
```

Valid examples:

```text
09:00
09:15
09:30
09:45
10:00
```

Invalid examples:

```text
09:05
09:10
09:12
09:37
```

All relevant operations must use controlled time in tests.

Do not make tests dependent on the actual system clock.

---

# 15. Future-Only Rule

Availability and appointment creation must follow the future-only rule defined in the API specification.

The AI agent must distinguish:

* past;
* current boundary;
* future.

Boundary conditions must be tested explicitly.

Do not rely on an implicit comparison such as:

```php
$date > now()
```

without considering the exact project semantics.

---

# 16. Availability Rules

The following invariants must always hold:

### No overlap

Two active availability periods for the same doctor must not overlap.

Allowed:

```text
10:00 ───── 11:00
11:00 ───── 12:00
```

Not allowed:

```text
10:00 ───── 11:00
10:30 ───── 11:30
```

### Soft-deleted availability

Soft-deleted availability does not participate in conflict detection or slot generation.

### Appointment containment

An appointment must be completely contained within an applicable availability period.

---

# 17. Appointment Conflict Rules

An appointment must not conflict with another active blocking appointment.

Blocking statuses:

```text
pending
confirmed
```

Non-blocking statuses:

```text
completed
cancelled
soft-deleted
```

The system must prevent:

### Doctor conflict

The same doctor cannot have overlapping blocking appointments.

### Patient conflict

The same patient cannot have overlapping blocking appointments.

Appointment boundaries touching each other are allowed where the specification permits them.

Example:

```text
10:00 ───── 11:00
11:00 ───── 12:00
```

is not an overlap.

---

# 18. Appointment Statuses

The only valid statuses are:

```text
pending
confirmed
completed
cancelled
```

The allowed transition graph is:

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

The AI agent must not introduce additional statuses.

Invalid transitions must return:

```http
409 Conflict
```

---

# 19. Cancellation Rule

The 24-hour cancellation rule must be implemented exactly as specified.

A confirmed appointment may only be cancelled when the required time threshold is satisfied.

The exact boundary must be tested.

Required test cases include:

```text
more than 24 hours before start
exactly 24 hours before start
less than 24 hours before start
```

Do not approximate the rule with a calendar-day comparison.

Use an exact datetime difference.

---

# 20. Slot Generation

Slot generation must be deterministic.

The algorithm must:

1. identify active availability;
2. ignore soft-deleted availability;
3. generate slots according to the 15-minute grid;
4. respect availability boundaries;
5. exclude slots blocked by active pending/confirmed appointments;
6. return deterministic ordering.

Do not persist generated slots unless the specification explicitly requires persistence.

Slots should normally be derived dynamically from availability and appointments.

---

# 21. Pagination

List endpoints that are defined as paginated must use Laravel pagination mechanisms.

The AI agent must not implement manual pagination unless required.

Pagination behavior must include tests for:

* default page;
* custom page;
* page size;
* total count;
* response metadata;
* deterministic ordering.

---

# 22. Ordering

List endpoints must have explicit deterministic ordering.

Do not rely on database default ordering.

Prefer an explicit:

```php
->orderBy(...)
```

or equivalent.

The ordering must be consistent with the API specification.

---

# 23. API Resources

API responses must use Laravel API Resources where specified.

Do not return arbitrary model arrays directly from controllers.

Example:

```php
return new DoctorResource($doctor);
```

Collections should use the appropriate resource collection behavior.

The API response structure must remain stable.

Do not expose database columns merely because they exist.

---

# 24. Models

Models must remain focused on persistence and relationships.

Use:

* typed relationships;
* appropriate casts;
* enums;
* SoftDeletes where required;
* guarded/fillable configuration appropriate to the project.

Avoid putting large workflows into Eloquent models.

A model may contain small, cohesive domain-related behavior where appropriate, but application workflows belong in Actions/Services.

---

# 25. Enums

Use PHP enums for finite domain values.

For example:

```php
enum AppointmentStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}
```

Do not spread raw status strings throughout the codebase.

---

# 26. Factories

Factories must produce valid domain data by default.

Factories should:

* use realistic values;
* create valid relationships;
* avoid accidental conflicts where possible;
* support explicit overrides in tests.

Tests should be able to construct specific edge cases through factory states or explicit attributes.

---

# 27. Seeders

Seeders must be deterministic.

Running:

```bash
php artisan db:seed
```

multiple times in a clean environment should produce the same logical dataset.

Seed data must be sufficient to demonstrate:

* doctors;
* patients;
* availability;
* appointments;
* relevant statuses.

Do not generate random, non-reproducible production-like data for the test task.

---

# 28. Testing Rules

Every meaningful feature must have tests.

The AI agent must not consider a feature complete merely because the endpoint works manually.

At minimum, test:

* happy path;
* validation;
* not found;
* business conflicts;
* boundary conditions;
* soft deletes where relevant;
* invalid state transitions;
* pagination where relevant.

Tests must follow the rules in:

```text
07-TESTING-SPEC.md
```

---

# 29. Test-First Behavior for Business Rules

For important business rules, the preferred workflow is:

```text
Write failing test
        ↓
Implement minimum behavior
        ↓
Run test
        ↓
Refactor
        ↓
Run full relevant suite
```

This is particularly important for:

* overlap detection;
* appointment conflicts;
* cancellation;
* status transitions;
* slot generation.

---

# 30. Time-Dependent Tests

Tests involving dates and times must use controlled time.

Use Laravel's time manipulation/testing facilities instead of depending on the real clock.

Example concept:

```php
Carbon::setTestNow(...);
```

or the corresponding Laravel-supported mechanism.

Every test that depends on "now" must make its time context explicit.

---

# 31. Database Tests

Tests must use an isolated test database.

SQLite is the project's selected database.

Use Laravel's database refresh mechanism as defined by the testing setup.

Tests must not depend on data created by another test.

Each test must be independently executable.

---

# 32. No Hidden Test Dependencies

Avoid:

```text
test A creates record
    ↓
test B expects record from test A
```

Prefer:

```text
test A
    └── creates its own data

test B
    └── creates its own data
```

A test suite must remain reliable regardless of test execution order.

---

# 33. Static Analysis

PHPStan/Larastan must be run regularly.

The AI agent must not suppress warnings without a concrete reason.

Avoid broad suppression such as:

```php
// @phpstan-ignore-next-line
```

unless:

1. the warning is understood;
2. the warning cannot reasonably be fixed;
3. the suppression is narrowly scoped;
4. the reason is documented when necessary.

Do not lower the PHPStan level simply to make the project pass.

---

# 34. Code Formatting

Laravel Pint is the formatting authority.

Run:

```bash
vendor/bin/pint
```

or:

```bash
vendor/bin/pint --test
```

depending on the workflow stage.

Do not manually introduce a separate formatting standard.

---

# 35. Dependency Management

Do not install a package merely because it provides a convenient shortcut.

Before adding a dependency, ask:

1. Is the functionality already available in Laravel?
2. Is the package explicitly required?
3. Does the package materially simplify a required part of the implementation?
4. Does the package introduce unnecessary complexity?

For this test project, the default assumption should be:

> Do not add another dependency unless there is a clear project-level reason.

---

# 36. No Scope Creep

The AI agent must not introduce:

* authentication;
* authorization;
* frontend;
* Vue;
* React;
* Livewire;
* Docker;
* Redis;
* queues;
* events;
* notifications;
* external APIs;
* payment systems;
* unnecessary repositories;
* unnecessary DTO layers;
* CQRS;
* full DDD infrastructure.

These are outside the defined scope unless explicitly requested later.

---

# 37. Refactoring Rules

Refactoring is allowed when it improves:

* correctness;
* readability;
* testability;
* maintainability;
* type safety;
* architectural consistency.

Do not refactor unrelated parts of the application while implementing a feature.

Avoid large speculative refactors.

A refactor should leave the test suite passing.

---

# 38. Existing Code Changes

Before modifying existing code:

1. read the file;
2. understand its responsibility;
3. identify dependent code;
4. determine whether tests already cover it;
5. make the smallest appropriate change.

Never overwrite an existing implementation simply because generating a new file is easier.

---

# 39. Routes

Routes must remain versioned:

```text
/api/v1/...
```

Do not introduce unversioned API routes.

Keep route definitions readable and grouped logically.

Route names should follow Laravel conventions where applicable.

---

# 40. API Compatibility

Once an endpoint is defined in `04-API-SPEC.md`, do not change:

* HTTP method;
* URI;
* parameter names;
* response structure;
* documented status codes;

without explicitly updating the specification.

Implementation must follow the documented API contract.

---

# 41. Git Workflow

Git must be used throughout development.

Before a milestone:

```bash
git status
```

After implementation and tests:

```bash
git diff
git status
```

Then commit the milestone.

Commit prefixes:

```text
feat:
fix:
test:
refactor:
docs:
chore:
```

Examples:

```text
feat: add doctor api
feat: add availability management
feat: add appointment creation
test: cover appointment conflicts
fix: enforce cancellation boundary
docs: update api examples
chore: configure phpstan
```

Avoid vague commits such as:

```text
update
changes
stuff
final
test
```

---

# 42. Milestone Commit Rule

A milestone should be committed only after its quality gate passes.

Minimum:

```bash
php artisan test
vendor/bin/phpstan analyse
vendor/bin/pint --test
```

If one fails, do not mark the milestone complete.

Fix the problem first.

---

# 43. Push Rule

After a verified milestone:

```bash
git push
```

The remote repository must represent a working project state.

Avoid pushing knowingly broken intermediate milestones.

---

# 44. AI Agent Output Format

After completing an implementation step, the AI agent should report:

### Implemented

Short list of changes.

### Tests

Commands executed and result.

### Quality

* PHPUnit
* PHPStan
* Pint

### Git

Commit created, if applicable.

### Next Step

The next planned milestone.

Example:

```text
Implemented
- Doctor CRUD
- DoctorResource
- DoctorRequest
- Doctor factory

Tests
- 8 feature tests passing

Quality
- PHPUnit: PASS
- PHPStan: PASS
- Pint: PASS

Git
- feat: add doctor api

Next
- Patient API
```

Do not provide long explanations unless requested.

---

# 45. When the AI Agent Encounters Ambiguity

If the specification does not define a required behavior, the AI agent must:

1. identify the missing decision;
2. avoid silently inventing a domain rule;
3. determine whether the behavior can safely follow Laravel conventions;
4. otherwise ask for a decision before implementation.

Examples of decisions that should not be invented:

* new appointment statuses;
* new cancellation rules;
* new conflict semantics;
* new API fields;
* new endpoints;
* new authentication behavior.

---

# 46. When the AI Agent Finds a Specification Conflict

Example:

```text
04-API-SPEC.md:
PATCH /appointments/{appointment}

05-IMPLEMENTATION-SPEC.md:
PUT /appointments/{appointment}
```

Do not silently choose one.

Report:

```text
Specification conflict detected:
- 04-API-SPEC.md requires PATCH
- 05-IMPLEMENTATION-SPEC.md requires PUT

Implementation paused until resolved.
```

The resolved decision should then be reflected consistently in the relevant documents.

---

# 47. Required Verification Before Completion

Before declaring the project complete, run:

```bash
php artisan test
vendor/bin/phpstan analyse
vendor/bin/pint --test
```

Also verify:

```bash
php artisan route:list
php artisan migrate:fresh --seed
```

The API should start successfully:

```bash
php artisan serve
```

The documented API examples should be manually executable.

---

# 48. Fresh Clone Verification

The final project must work from a fresh clone.

Verification sequence:

```bash
git clone <repository>
cd <repository>

composer install

cp .env.example .env

php artisan key:generate

touch database/database.sqlite

php artisan migrate:fresh --seed

php artisan test

vendor/bin/phpstan analyse

vendor/bin/pint --test
```

The exact Windows equivalent may be used where `touch` is unavailable.

The important requirement is that no undocumented local state is necessary.

---

# 49. Definition of AI Implementation Done

A feature is complete only when all of the following are true:

* implementation exists;
* architecture is respected;
* validation exists;
* business rules are implemented;
* relevant tests exist;
* edge cases are tested;
* soft-delete behavior is correct;
* error responses follow the API contract;
* PHPStan passes;
* Pint passes;
* relevant PHPUnit tests pass;
* documentation is updated when necessary;
* Git milestone is committed.

"Endpoint works in Postman" alone is not sufficient.

---

# 50. Final AI Coding Checklist

Before finishing the project, verify:

## Architecture

* [ ] Controllers are thin.
* [ ] Business logic is outside controllers.
* [ ] FormRequests are used where appropriate.
* [ ] API Resources are used.
* [ ] Models remain focused.
* [ ] Enums are used for finite domain states.

## Database

* [ ] SQLite is used.
* [ ] Foreign keys are defined.
* [ ] Required indexes exist.
* [ ] Soft deletes are implemented.
* [ ] Relationships are correct.
* [ ] Migrations run from a clean database.

## API

* [ ] API is versioned under `/api/v1`.
* [ ] Routes match the API specification.
* [ ] Request validation is implemented.
* [ ] Response structures are stable.
* [ ] Pagination works where required.
* [ ] Ordering is deterministic.
* [ ] Error status codes are correct.

## Business Rules

* [ ] Availability overlap is prevented.
* [ ] Appointment must fit availability.
* [ ] Doctor conflicts are prevented.
* [ ] Patient conflicts are prevented.
* [ ] Pending/confirmed appointments block slots.
* [ ] Completed/cancelled appointments do not block slots.
* [ ] Status transitions are enforced.
* [ ] Cancellation rule is enforced.
* [ ] Future-only rules are enforced.
* [ ] 15-minute boundaries are enforced.
* [ ] Soft-deleted records are ignored by active business logic.

## Testing

* [ ] Feature tests exist.
* [ ] Unit tests exist where useful.
* [ ] Validation is tested.
* [ ] Not-found behavior is tested.
* [ ] Conflict behavior is tested.
* [ ] Boundary conditions are tested.
* [ ] Time-dependent behavior uses controlled time.
* [ ] Tests are independent.
* [ ] Pagination is tested.
* [ ] Resources are tested where appropriate.

## Quality

* [ ] PHPUnit passes.
* [ ] PHPStan passes.
* [ ] Pint passes.
* [ ] No unexplained suppressions exist.
* [ ] No unnecessary dependencies were introduced.

## Git

* [ ] Repository is initialized.
* [ ] `.gitignore` is correct.
* [ ] Milestones have meaningful commits.
* [ ] Commits use consistent prefixes.
* [ ] Verified milestones are pushed.
* [ ] Fresh clone setup has been verified.

---

# 51. Core AI Rule

The AI agent must optimize for:

```text
Correctness
    >
Completeness
    >
Simplicity
    >
Convenience
```

The agent must never sacrifice a documented business rule merely to produce shorter code.

The goal is not to generate the largest possible Laravel application.

The goal is to produce a small, correct, testable, maintainable backend that demonstrably satisfies the test assignment.
