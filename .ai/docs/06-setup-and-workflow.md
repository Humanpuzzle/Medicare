# Medicare Backend Test — Setup and Workflow Specification

## 1. Purpose

This document defines the standard setup, local development workflow, quality checks, testing workflow, database initialization, API startup, and Git workflow for the Medicare Backend Test project.

The goal is to provide a reproducible development environment while keeping the setup intentionally simple and aligned with the project scope.

The application is a backend-only Laravel REST API using:

* PHP 8.3+
* Laravel 11+
* SQLite
* Composer
* PHPUnit
* Laravel Pint
* PHPStan + Larastan
* Git

The API is exposed under:

```text
/api/v1
```

All application date/time handling is performed in UTC.

---

# 2. Prerequisites

## 2.1 Required software

The following software must be available on the development machine:

| Tool           | Requirement                                           |
| -------------- | ----------------------------------------------------- |
| PHP            | 8.3+                                                  |
| Composer       | Current stable version                                |
| Git            | Current stable version                                |
| SQLite         | SQLite 3 / PHP SQLite extension                       |
| Code editor    | Any suitable editor                                   |
| PHP extensions | Laravel-required extensions, including SQLite support |

The project does not require:

* MySQL
* MariaDB
* PostgreSQL
* Node.js
* npm
* Docker
* Redis
* a frontend runtime

The application is backend-only.

---

# 3. Manual Installation and Configuration

This section contains only the prerequisites that must exist on the development machine before the project can be run.

Most Laravel development environments already contain these components. They should be verified rather than reinstalled unnecessarily.

## 3.1 Verify PHP

Run:

```bash
php -v
```

The version must be PHP 8.3 or newer.

Example:

```text
PHP 8.3.x
```

or newer.

The CLI PHP version is important because Composer, Artisan, PHPUnit, PHPStan, and Pint use the CLI PHP installation.

---

## 3.2 Verify Composer

Run:

```bash
composer --version
```

Composer must be available globally.

Verify that Composer uses the expected PHP installation:

```bash
composer diagnose
```

If multiple PHP installations exist on the machine, verify:

```bash
where php
```

on Windows, or:

```bash
which php
```

on Linux/macOS.

The PHP executable used by Composer and the project should be the intended PHP 8.3+ installation.

---

## 3.3 Verify Git

Run:

```bash
git --version
```

Git is required for version control and milestone-based submission.

---

## 3.4 Verify SQLite support

PHP must have SQLite support enabled.

Verify:

```bash
php -m
```

The following modules should be available:

```text
PDO
pdo_sqlite
sqlite3
```

On Windows, the PHP installation may require the SQLite extensions to be enabled in `php.ini`.

The project uses SQLite as its database engine.

---

## 3.5 Required PHP extensions

The PHP installation must provide the extensions required by Laravel and the project.

At minimum, verify the relevant standard Laravel extensions, including:

```text
ctype
curl
fileinfo
mbstring
openssl
PDO
pdo_sqlite
sqlite3
tokenizer
xml
```

The exact extension set may depend on the installed PHP/Laravel version.

---

# 4. Project Creation

If the repository already contains the Laravel application, skip this section and continue with the dependency installation.

For a new project:

```bash
composer create-project laravel/laravel medicare-backend-test
```

Enter the project:

```bash
cd medicare-backend-test
```

Verify Laravel:

```bash
php artisan --version
```

The resulting application must use Laravel 11 or newer.

---

# 5. Dependency Installation

If the project was cloned from Git, install Composer dependencies:

```bash
composer install
```

The project must use the dependency versions defined by `composer.lock`.

Do not manually install packages that are already present in the lock file.

---

## 5.1 Larastan

PHPStan + Larastan are required for static analysis.

If Larastan has not yet been added to the project:

```bash
composer require --dev larastan/larastan
```

The final project must contain the required PHPStan/Larastan configuration.

The selected analysis level should remain consistent with the implementation specification.

Target:

```text
PHPStan/Larastan level 5–6
```

---

## 5.2 PHPUnit

Laravel provides PHPUnit integration as part of the standard Laravel application setup.

Verify:

```bash
php artisan test
```

If the project dependencies are correctly installed, PHPUnit should execute through Laravel.

---

## 5.3 Laravel Pint

Laravel Pint is used for code formatting.

Verify:

```bash
vendor/bin/pint --version
```

Pint should be used before commits.

---

# 6. Environment Configuration

Create the local environment file if it does not already exist:

```bash
cp .env.example .env
```

On Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

Generate the application key:

```bash
php artisan key:generate
```

The `.env` file is local configuration and must not be committed.

---

# 7. SQLite Configuration

The project uses SQLite.

Create the database file.

On Linux/macOS:

```bash
touch database/database.sqlite
```

On Windows PowerShell:

```powershell
New-Item database/database.sqlite -ItemType File
```

Configure `.env`:

```env
DB_CONNECTION=sqlite
```

SQLite does not require:

```env
DB_HOST=
DB_PORT=
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=
```

for the normal file-based configuration.

The resulting connection must point to:

```text
database/database.sqlite
```

The actual SQLite file is local development state and should not be committed to Git unless explicitly required.

---

# 8. Application Timezone

The final project specification defines UTC as the internal application timezone.

The application configuration must therefore use:

```text
UTC
```

Verify the Laravel application timezone configuration.

All persisted appointment and availability timestamps must be handled consistently in UTC.

Do not introduce application-level local timezone conversion unless it is explicitly required by the API contract.

---

# 9. API Routing Setup

The project uses:

```text
/api/v1
```

Laravel 11 applications do not necessarily contain an API route file in the same way as older Laravel applications.

The project must expose an API route file:

```text
routes/api.php
```

The API route file must be registered in the Laravel application bootstrap configuration.

The API routes should then use the version prefix:

```php
Route::prefix('v1')->group(function (): void {
    // API routes
});
```

The resulting URLs are:

```text
/api/v1/...
```

## Important

Do not install authentication-related API scaffolding merely to obtain an API route file.

Authentication and authorization are explicitly out of scope for this project.

In particular, the setup must not introduce Sanctum or another authentication system unless the project specification is changed.

---

# 10. Application Configuration Verification

After configuring the environment, clear cached configuration:

```bash
php artisan optimize:clear
```

Verify the application boots:

```bash
php artisan about
```

The command should complete without configuration or dependency errors.

---

# 11. Database Migration

Run the migrations:

```bash
php artisan migrate
```

The migration process must create the complete database schema defined by:

```text
02-DATABASE-SPEC.md
```

The schema must include the required tables and constraints for:

* doctors
* patients
* availabilities
* appointments

and the required soft-delete columns where specified.

---

# 12. Database Reset During Development

For a clean development database:

```bash
php artisan migrate:fresh
```

If seeders are implemented:

```bash
php artisan migrate:fresh --seed
```

`migrate:fresh` must only be used for local development or test environments where destroying existing data is acceptable.

It must not be used against a production database.

---

# 13. Seeders

The project may provide development seed data for:

* doctors
* patients
* availabilities
* appointments

Seed data should be deterministic enough to make local API testing reproducible.

Run:

```bash
php artisan db:seed
```

or:

```bash
php artisan migrate:fresh --seed
```

The seeder must respect all domain constraints.

For example, seeded appointments must not violate:

* availability containment
* doctor conflicts
* patient conflicts
* future appointment requirements
* appointment state rules

Seeders are development/test support and are not part of the API domain itself.

---

# 14. Starting the Application

Start the Laravel development server:

```bash
php artisan serve
```

The default local address is:

```text
http://127.0.0.1:8000
```

The API base URL is therefore:

```text
http://127.0.0.1:8000/api/v1
```

---

# 15. Basic API Smoke Test

After starting the application, verify that an API endpoint responds.

Example:

```http
GET /api/v1/doctors
```

For example:

```bash
curl http://127.0.0.1:8000/api/v1/doctors
```

The response must be valid JSON.

Collection endpoints must use the pagination contract defined in the API specification.

---

# 16. Collection Query Examples

The following examples demonstrate the intended collection-style API usage.

## 16.1 List doctors

```http
GET /api/v1/doctors
```

Example:

```bash
curl "http://127.0.0.1:8000/api/v1/doctors"
```

---

## 16.2 Paginate doctors

```http
GET /api/v1/doctors?page=1&per_page=25
```

Example:

```bash
curl "http://127.0.0.1:8000/api/v1/doctors?page=1&per_page=25"
```

The default page size is:

```text
25
```

The maximum page size is:

```text
100
```

Requests above the configured maximum must be handled according to the API specification.

---

## 16.3 List patients

```http
GET /api/v1/patients
```

Example:

```bash
curl "http://127.0.0.1:8000/api/v1/patients"
```

---

## 16.4 List availabilities

```http
GET /api/v1/availabilities
```

Example:

```bash
curl "http://127.0.0.1:8000/api/v1/availabilities"
```

Availability filtering and date/range parameters must follow:

```text
04-API-SPEC.md
```

---

## 16.5 List appointments

```http
GET /api/v1/appointments
```

Example:

```bash
curl "http://127.0.0.1:8000/api/v1/appointments"
```

Appointment filtering must follow the API contract.

Where status filtering is defined:

```http
GET /api/v1/appointments?status=confirmed
```

Pagination remains available:

```http
GET /api/v1/appointments?status=confirmed&page=1&per_page=25
```

---

# 17. API Contract Reference

The setup workflow does not redefine API behavior.

The authoritative endpoint definitions, request structures, response structures, query parameters, and error responses are defined in:

```text
04-API-SPEC.md
```

The implementation must not introduce undocumented API behavior merely because it is convenient during local development.

The specification hierarchy is:

```text
01-PROJECT-SPEC.md
        ↓
02-DATABASE-SPEC.md
        ↓
03-IMPLEMENTATION / architecture decisions
        ↓
04-API-SPEC.md
        ↓
05-IMPLEMENTATION-SPEC.md
        ↓
06-SETUP-AND-WORKFLOW.md
```

If a workflow example conflicts with the API specification, the API specification takes precedence.

---

# 18. Development Workflow

The recommended development workflow is:

```text
Install / configure
      ↓
Create or clone project
      ↓
composer install
      ↓
Configure .env
      ↓
Configure SQLite
      ↓
Run migrations
      ↓
Seed development data
      ↓
Start API
      ↓
Implement domain/database layer
      ↓
Implement API
      ↓
Implement business rules
      ↓
Write tests
      ↓
Run quality checks
      ↓
Commit milestone
      ↓
Push milestone
```

The implementation should follow the implementation order defined in:

```text
05-IMPLEMENTATION-SPEC.md
```

---

# 19. Recommended Implementation Order

The implementation should proceed in the following logical order.

## Phase 1 — Laravel foundation

Implement:

* Laravel application
* SQLite configuration
* environment configuration
* API routing
* base application configuration
* development tooling

Verify:

```bash
php artisan about
php artisan migrate
php artisan test
```

---

## Phase 2 — Database and models

Implement:

* Doctor migration/model
* Patient migration/model
* Availability migration/model
* Appointment migration/model
* enum definitions
* relationships
* soft deletes
* required database indexes and constraints

Run:

```bash
php artisan migrate:fresh
```

Then:

```bash
php artisan test
```

---

## Phase 3 — Validation and services

Implement:

* FormRequests
* AvailabilityService
* AppointmentService
* state transition rules
* conflict detection
* availability containment
* duration validation
* cancellation rules
* email normalization

The business rules should be centralized in services rather than duplicated across controllers.

---

## Phase 4 — API resources and controllers

Implement:

* API Resources
* Doctor controller
* Patient controller
* Availability controller
* Appointment controller
* required slot endpoint/controller

Controllers should remain thin.

The normal request flow should be approximately:

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

---

## Phase 5 — Tests

Implement feature tests covering:

* doctor creation/listing
* patient creation/listing
* availability creation
* availability validation
* availability overlap
* appointment creation
* appointment containment
* appointment duration
* doctor conflicts
* patient conflicts
* appointment state transitions
* cancellation boundary
* cancellation reason behavior
* email uniqueness
* email normalization
* pagination
* available slot generation

Time-dependent tests should use Laravel/Carbon test-time facilities rather than relying on the actual current time.

---

## Phase 6 — Quality checks

Before the milestone is considered complete, run:

```bash
php artisan test
```

```bash
vendor/bin/pint --test
```

```bash
vendor/bin/phpstan analyse
```

If Pint reports formatting issues:

```bash
vendor/bin/pint
```

Then rerun:

```bash
vendor/bin/pint --test
```

All three quality checks should pass before the milestone is committed.

---

# 20. Testing Workflow

## 20.1 Run the complete test suite

```bash
php artisan test
```

This is the standard test command.

---

## 20.2 Run a specific test file

Example:

```bash
php artisan test tests/Feature/AppointmentTest.php
```

The exact test filenames depend on the implemented test suite.

---

## 20.3 Run a filtered test

Example:

```bash
php artisan test --filter=Appointment
```

This is useful while developing a specific domain area.

---

## 20.4 Test database

Tests should use an isolated test database configuration.

Tests must not depend on the developer's local development database state.

Each test should establish the state it requires.

Where appropriate, use Laravel's database-refreshing test facilities.

---

# 21. Time-Dependent Testing

Appointments and availabilities contain future date/time rules.

Tests must therefore not depend on the actual system clock.

Use Carbon's test-time functionality.

Conceptually:

```php
Carbon::setTestNow(...);
```

After the test:

```php
Carbon::setTestNow();
```

This allows deterministic testing of:

* future appointments
* cancellation boundaries
* exactly 24-hour cancellation
* less-than-24-hour cancellation
* availability dates
* slot generation

The exact testing implementation should follow the implementation specification.

---

# 22. Static Analysis Workflow

Run:

```bash
vendor/bin/phpstan analyse
```

PHPStan/Larastan must be configured for the project source code and test code according to the project's chosen configuration.

The target analysis level is:

```text
5–6
```

The implementation should resolve real static-analysis problems rather than hiding them through broad ignore rules.

---

# 23. Formatting Workflow

Check formatting:

```bash
vendor/bin/pint --test
```

Apply formatting:

```bash
vendor/bin/pint
```

Pint should be run before committing a milestone.

---

# 24. Full Pre-Commit Quality Check

Before a milestone commit:

```bash
composer install
```

if dependencies changed, followed by:

```bash
php artisan optimize:clear
php artisan migrate:fresh --seed
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
```

If the seeders are not yet implemented, use:

```bash
php artisan migrate:fresh
```

instead.

The exact command sequence can be shortened once the project is stable, but the final submission should pass the complete verification set.

---

# 25. Git Initialization

If the repository does not yet exist:

```bash
git init
```

Check the repository:

```bash
git status
```

Laravel's `.gitignore` should exclude generated/local files such as:

```text
.env
/vendor
```

The local SQLite database should also remain untracked unless the assignment explicitly requires committing it.

---

# 26. Initial Git Commit

After the Laravel foundation has been created and verified:

```bash
git add .
```

Review:

```bash
git status
```

Commit:

```bash
git commit -m "chore: initialize Laravel backend"
```

The first commit should contain only the initial project foundation.

---

# 27. Remote Repository

Add the remote repository:

```bash
git remote add origin <repository-url>
```

Verify:

```bash
git remote -v
```

Rename the default branch if necessary:

```bash
git branch -M main
```

Push:

```bash
git push -u origin main
```

The actual repository URL is intentionally not defined in this specification.

---

# 28. Milestone-Based Git Workflow

Development should be pushed in meaningful milestones rather than as one final unstructured commit.

Recommended milestones:

## Milestone 1 — Project foundation

Contains:

* Laravel application
* SQLite configuration
* environment setup
* API routing
* development tooling
* basic README

Example commit:

```bash
git add .
git commit -m "chore: set up Laravel API foundation"
git push
```

---

## Milestone 2 — Database and domain models

Contains:

* migrations
* models
* relationships
* enums
* soft deletes
* database indexes/constraints

Example:

```bash
git add .
git commit -m "feat: implement database schema and domain models"
git push
```

---

## Milestone 3 — Availability and appointment business rules

Contains:

* FormRequests
* AvailabilityService
* AppointmentService
* validation
* conflicts
* state transitions
* cancellation rules
* slot generation

Example:

```bash
git add .
git commit -m "feat: implement availability and appointment rules"
git push
```

---

## Milestone 4 — REST API

Contains:

* controllers
* API Resources
* endpoints
* pagination
* filtering
* API error handling

Example:

```bash
git add .
git commit -m "feat: implement versioned REST API"
git push
```

---

## Milestone 5 — Tests and quality

Contains:

* feature tests
* edge-case tests
* static-analysis fixes
* formatting
* test infrastructure improvements

Example:

```bash
git add .
git commit -m "test: cover API and business rules"
git push
```

---

## Milestone 6 — Final documentation

Contains:

* README
* setup instructions
* API usage examples
* final cleanup
* final quality fixes

Example:

```bash
git add .
git commit -m "docs: finalize project documentation"
git push
```

The exact number of commits may be adjusted during development. The important requirement is that commits represent coherent implementation milestones.

---

# 29. Daily Development Cycle

A normal implementation cycle should follow:

```text
1. Select one implementation task
2. Implement the smallest coherent change
3. Run the relevant tests
4. Run static analysis when the change is substantial
5. Run Pint
6. Inspect git diff
7. Commit the completed milestone/change
8. Push when the milestone is ready
```

Example:

```bash
php artisan test --filter=Appointment
```

then:

```bash
vendor/bin/pint --test
```

then:

```bash
vendor/bin/phpstan analyse
```

then:

```bash
git diff
git status
```

---

# 30. Database Development Workflow

When database structure changes:

```text
Modify migration
      ↓
Update model
      ↓
Update relationships
      ↓
Update factories/seeders if required
      ↓
Update tests
      ↓
migrate:fresh
      ↓
Run tests
```

Do not manually modify the SQLite database schema.

Schema changes belong in migrations.

---

# 31. API Development Workflow

When an endpoint changes:

```text
Update API specification
        ↓
Update FormRequest
        ↓
Update Controller
        ↓
Update Service
        ↓
Update Resource
        ↓
Update Feature Tests
        ↓
Run API tests
```

The implementation should not silently diverge from:

```text
04-API-SPEC.md
```

---

# 32. Business Rule Development Workflow

Cross-entity and domain rules should be implemented in services.

Examples include:

* doctor availability overlap
* appointment availability containment
* doctor appointment conflict
* patient appointment conflict
* duration validation
* appointment state transitions
* cancellation timing

The controller should not contain the full business-rule implementation.

Preferred:

```text
Controller
    ↓
AppointmentService
    ↓
Domain validation
    ↓
Database transaction
```

Not:

```text
Controller
    ↓
large collection of domain-specific conditionals
```

---

# 33. Transaction Workflow

Appointment creation must use a database transaction because multiple validation/write operations participate in one logical operation.

The implementation should follow:

```text
Validate request
      ↓
Begin transaction
      ↓
Re-check relevant domain constraints
      ↓
Create appointment
      ↓
Commit
```

If a business-rule failure occurs:

```text
Rollback
      ↓
Return structured API error
```

The same principle applies to other multi-step writes where atomicity is required.

---

# 34. Local API Verification

After implementation of an endpoint, verify it through an HTTP client.

Possible clients:

* curl
* PowerShell
* Postman
* Insomnia
* browser for simple GET endpoints

For example:

```bash
curl "http://127.0.0.1:8000/api/v1/doctors"
```

The response should be inspected for:

* HTTP status
* JSON structure
* pagination metadata where applicable
* resource fields
* validation errors
* business-rule errors

---

# 35. Error Verification

For each business rule, verify both:

1. successful valid input
2. rejected invalid input

Examples:

```text
Valid availability
Invalid overlapping availability
```

```text
Valid appointment
Appointment outside availability
```

```text
Valid appointment
Doctor conflict
```

```text
Valid appointment
Patient conflict
```

```text
Valid cancellation
Cancellation under 24 hours
```

```text
Valid state transition
Invalid state transition
```

This ensures that business rules are not tested only through the happy path.

---

# 36. README Requirements

The final repository must contain a `README.md`.

The README must provide enough information for another developer to run the project without inspecting the source code first.

It should contain:

## Project overview

Brief explanation of the Medicare Backend Test API.

## Requirements

For example:

```text
PHP 8.3+
Composer
SQLite
Git
```

## Installation

Example:

```bash
composer install
cp .env.example .env
php artisan key:generate
```

## Database

Explain SQLite configuration and migration:

```bash
php artisan migrate
```

## Seed data

If seeders exist:

```bash
php artisan db:seed
```

or:

```bash
php artisan migrate:fresh --seed
```

## Run the API

```bash
php artisan serve
```

## Test

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

## API

Document the API base:

```text
/api/v1
```

Provide a few representative GET/POST examples based on `04-API-SPEC.md`.

## Design decisions

Briefly document relevant decisions such as:

* SQLite
* UTC
* soft deletes
* service-based business rules
* appointment state machine
* pagination
* API versioning

---

# 37. Files That Must Not Be Committed

The repository must not contain local secrets or generated dependencies.

At minimum:

```text
.env
/vendor
```

The local SQLite database should normally also remain untracked:

```text
database/database.sqlite
```

Do not commit:

* API keys
* passwords
* local machine configuration
* IDE-specific private configuration
* generated cache files
* local database state

`.env.example` should contain only safe example configuration.

---

# 38. Configuration Cache

When changing `.env` or configuration:

```bash
php artisan optimize:clear
```

If required, verify:

```bash
php artisan config:clear
```

Do not rely on stale cached configuration during development.

---

# 39. Troubleshooting

## 39.1 SQLite driver missing

If Laravel reports an error such as:

```text
could not find driver
```

verify:

```bash
php -m
```

and confirm:

```text
pdo_sqlite
sqlite3
```

are enabled for the CLI PHP installation.

---

## 39.2 Wrong PHP version

Verify:

```bash
php -v
```

and:

```bash
where php
```

on Windows, or:

```bash
which php
```

on Linux/macOS.

Multiple PHP installations can result in Composer and Artisan using different PHP versions.

---

## 39.3 Environment changes not recognized

Run:

```bash
php artisan optimize:clear
```

Then retry the command.

---

## 39.4 Database schema is inconsistent

For local development:

```bash
php artisan migrate:fresh --seed
```

If seeders are not yet implemented:

```bash
php artisan migrate:fresh
```

---

## 39.5 Tests use unexpected data

Tests must not depend on the local development database.

Verify that the test environment creates its own database state and that tests use their required factories/fixtures.

---

# 40. Final Verification Checklist

Before considering the implementation complete, verify:

## Environment

* [ ] PHP 8.3+ is available
* [ ] Composer is available
* [ ] Git is available
* [ ] SQLite is available
* [ ] `pdo_sqlite` is enabled
* [ ] required Laravel PHP extensions are enabled

## Laravel

* [ ] application boots successfully
* [ ] `.env` exists locally
* [ ] application key exists
* [ ] application timezone is UTC
* [ ] API routing is registered
* [ ] API version is `/api/v1`

## Database

* [ ] SQLite database is created
* [ ] migrations run successfully
* [ ] migrations can be recreated from scratch
* [ ] soft-delete columns are present where required
* [ ] database relationships and constraints are correct

## Domain

* [ ] Doctor implemented
* [ ] Patient implemented
* [ ] Availability implemented
* [ ] Appointment implemented
* [ ] appointment status enum implemented
* [ ] state transitions enforced
* [ ] cancellation rules enforced
* [ ] availability overlap rules enforced
* [ ] appointment conflict rules enforced
* [ ] duration rules enforced
* [ ] UTC handling enforced
* [ ] email normalization implemented

## API

* [ ] API endpoints match `04-API-SPEC.md`
* [ ] request validation is implemented
* [ ] API Resources are used
* [ ] collection endpoints are paginated
* [ ] default page size is 25
* [ ] maximum page size is 100
* [ ] deterministic ordering is used
* [ ] structured errors are returned

## Tests

* [ ] feature tests exist
* [ ] happy paths are covered
* [ ] invalid input is covered
* [ ] business-rule failures are covered
* [ ] state transitions are covered
* [ ] cancellation boundary is covered
* [ ] conflict detection is covered
* [ ] pagination is covered
* [ ] time-dependent behavior is deterministic

## Code quality

* [ ] `php artisan test` passes
* [ ] `vendor/bin/pint --test` passes
* [ ] `vendor/bin/phpstan analyse` passes
* [ ] no unnecessary suppressions exist
* [ ] controllers remain thin
* [ ] business logic is centralized in services
* [ ] no unnecessary architectural patterns were introduced

## Git

* [ ] `.env` is ignored
* [ ] `vendor/` is ignored
* [ ] local SQLite database is ignored
* [ ] meaningful commits exist
* [ ] milestones have been pushed
* [ ] repository contains a clean final state

## Documentation

* [ ] README exists
* [ ] installation is documented
* [ ] SQLite setup is documented
* [ ] migration/seed commands are documented
* [ ] API startup is documented
* [ ] test commands are documented
* [ ] static-analysis command is documented
* [ ] formatting command is documented
* [ ] API base URL is documented
* [ ] important design decisions are documented

---

# 41. Definition of Setup Complete

The project setup is considered complete when a clean checkout can be prepared with the documented commands and the following workflow succeeds:

```bash
composer install
```

```bash
php artisan key:generate
```

```bash
php artisan migrate
```

```bash
php artisan test
```

```bash
vendor/bin/pint --test
```

```bash
vendor/bin/phpstan analyse
```

and the API can be started with:

```bash
php artisan serve
```

with endpoints available under:

```text
/api/v1
```

The setup must not require undocumented manual modifications or dependencies.

---

# 42. Final Development Principle

The project should remain intentionally simple.

The implementation should prefer:

```text
Laravel conventions
        +
clear models
        +
FormRequests
        +
focused services
        +
API Resources
        +
feature tests
```

over unnecessary abstraction.

The setup workflow exists to make the implementation reproducible and verifiable, not to introduce additional infrastructure.

The final implementation must remain aligned with:

```text
01-PROJECT-SPEC.md
02-DATABASE-SPEC.md
04-API-SPEC.md
05-IMPLEMENTATION-SPEC.md
```

Those documents define the project's functional and technical requirements. This document defines how that implementation is installed, run, tested, validated, committed, and delivered.
