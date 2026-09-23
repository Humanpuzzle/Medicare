# 09 — Manual Setup and Developer Tasks

## 1. Purpose

This document defines the tasks that must be performed manually by the developer and separates them from tasks that can be handled by an AI coding agent.

The goal is to make the project setup reproducible while avoiding unnecessary manual work.

The project should follow this principle:

```text
Developer
    ↓
Prepare machine / accounts / repository
    ↓
AI Coding Agent
    ↓
Implement application
    ↓
Developer + AI
    ↓
Verify / review / push
```

---

# 2. Responsibility Matrix

| Task                          | Developer   | AI Agent |
| ----------------------------- | :-------:   | :------: |
| Install PHP                   |     ✅     |     ❌    |
| Install Composer              |     ✅     |     ❌    |
| Install Git                   |     ✅     |     ❌    |
| Verify PHP extensions         |     ✅     |    🔎    |
| Install Laravel project       |  optional   |     ✅    |
| Configure Laravel             |  optional   |     ✅    |
| Create migrations             |     ❌     |     ✅    |
| Create models                 |     ❌     |     ✅    |
| Create factories              |     ❌     |     ✅    |
| Create seeders                |     ❌     |     ✅    |
| Create API                    |     ❌     |     ✅    |
| Create tests                  |     ❌     |     ✅    |
| Configure PHPStan             |     ❌     |     ✅    |
| Configure Pint                |     ❌     |     ✅    |
| Run tests                     |  optional |     ✅    |
| Review implementation         |     ✅     |     ❌    |
| Create GitHub repository      |     ✅     |     ❌    |
| Configure Git remote          |  optional |     ✅    |
| Push commits                  |  optional |     ✅    |
| Final manual API verification |     ✅     |     ❌    |
| Final project submission      |     ✅     |     ❌    |

---

# 3. Developer Prerequisites

The following software must exist on the development machine before implementation begins.

## Required

### PHP

Required PHP version:

```text
PHP 8.4+
```

The exact Laravel-supported version must match the version selected in the project.

Verify:

```bash
php -v
```

Expected example:

```text
PHP 8.4.x
```

---

## Composer

Composer is required for Laravel and PHP dependencies.

Verify:

```bash
composer --version
```

---

## Git

Git is required for version control and milestone commits.

Verify:

```bash
git --version
```

---

## SQLite

SQLite is the project database.

The PHP SQLite extensions must be enabled.

Verify:

```bash
php -m
```

Look for:

```text
pdo_sqlite
sqlite3
```

If either extension is missing, it must be enabled in the active PHP configuration.

---

# 4. PHP Extensions

The exact required extensions depend on the selected Laravel version and installed packages.

The baseline environment should include:

```text
ctype
curl
dom
fileinfo
filter
hash
mbstring
openssl
pcre
PDO
pdo_sqlite
session
tokenizer
xml
```

Additional extensions may be required by Composer packages.

Check the complete environment with:

```bash
php -m
```

and:

```bash
php --ini
```

The second command is particularly important on Windows systems because multiple PHP installations may exist.

---

# 5. Windows Environment Verification

Because the project may be developed on Windows, verify which PHP executable is actually being used.

Run:

```bash
where php
```

Then:

```bash
php -v
```

If multiple PHP installations are returned, the first one in the PATH is normally the CLI version being used.

The following commands must refer to the intended PHP installation:

```bash
php
composer
php artisan
```

Do not assume that the PHP version configured in XAMPP is automatically the same PHP version used by the CLI.

---

# 6. Git Configuration

Before creating the repository, configure Git identity if it has not already been configured.

Check:

```bash
git config --global user.name
git config --global user.email
```

If necessary:

```bash
git config --global user.name "Your Name"
git config --global user.email "your@email.example"
```

The actual developer identity must be used.

Do not put credentials, access tokens, or passwords into the repository.

---

# 7. GitHub / Git Remote

The developer should manually create the remote repository if required by the assignment.

Recommended:

```text
Repository visibility:
Private or Public according to assignment requirements
```

The repository should initially be empty if the local Laravel project already exists.

Do not create conflicting README/license/gitignore files remotely if they are already going to be created locally.

---

# 8. Repository Initialization

If the Laravel project is created locally:

```bash
git init
```

Then:

```bash
git status
```

The developer or AI agent may add the remote:

```bash
git remote add origin <repository-url>
```

Verify:

```bash
git remote -v
```

The repository URL must not be hardcoded into application code.

---

# 9. Laravel Project Creation

The Laravel project itself does not need to be created manually if the AI coding workflow is used.

The agent can execute the appropriate Laravel installation command.

The developer must decide the project directory and confirm that the correct PHP/Composer environment is active.

Example:

```bash
composer create-project laravel/laravel <project-name>
```

The exact Laravel version must match the project specification.

---

# 10. Laravel Boost

Laravel Boost is **not required** for this project.

The current project intentionally uses the standard Laravel development stack.

Do not install Laravel Boost unless a later requirement explicitly justifies it.

---

# 11. Frontend

No frontend framework is required.

The project is a backend/API test assignment.

Do not manually install:

```text
React
Vue
Livewire
Inertia
Tailwind
Shadcn/UI
```

unless the assignment specification changes.

A minimal browser UI is not part of the current scope.

API testing can be performed with:

* HTTP client;
* Postman;
* Insomnia;
* curl;
* IDE REST client.

---

# 12. Node.js / npm

Node.js is not required for the core backend implementation if the project does not use frontend tooling.

Do not install Node.js solely because Laravel projects commonly contain frontend tooling.

If the selected Laravel installation includes `package.json`, that does not automatically mean a frontend must be implemented.

Only install/use Node.js if an actual project dependency requires it.

---

# 13. Database Setup

The project uses SQLite.

The developer must ensure that the SQLite PHP extensions are available.

The database file can be created with:

```text
database/database.sqlite
```

On Windows PowerShell:

```powershell
New-Item database/database.sqlite -ItemType File
```

Or through the appropriate IDE/file manager.

The actual database file must not be committed to Git.

---

# 14. Environment File

The developer or AI agent creates the environment configuration from:

```text
.env.example
```

using:

```bash
cp .env.example .env
```

On Windows, the equivalent file-copy operation may be used.

Then:

```bash
php artisan key:generate
```

The `.env` file must remain local.

It must be included in `.gitignore`.

---

# 15. SQLite Environment Configuration

The `.env` configuration should point Laravel to SQLite.

Conceptually:

```env
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/database/database.sqlite
```

Where supported by the Laravel configuration, the relative/default SQLite configuration may be used.

The final configuration must be verified by running:

```bash
php artisan migrate
```

---

# 16. Initial Application Installation

Once the environment is ready:

```bash
composer install
```

Then:

```bash
php artisan key:generate
```

Then:

```bash
php artisan migrate
```

At this point Laravel should be able to connect to SQLite successfully.

---

# 17. Developer Manual Checkpoint

Before AI implementation begins, the developer should verify:

```bash
php -v
composer --version
git --version
php artisan --version
php -m
```

Then:

```bash
php artisan about
```

The developer should confirm:

* PHP version is correct;
* Composer works;
* Git works;
* Laravel works;
* SQLite is available;
* project directory is correct.

---

# 18. What the AI Agent Should Install

Once the base Laravel project exists, the AI agent may install project-level Composer dependencies.

Expected development dependencies include:

```text
PHPUnit
PHPStan
Larastan
Laravel Pint
```

The exact packages and versions must be compatible with the selected Laravel version.

The agent must not blindly install packages without checking `composer.json`.

---

# 19. Dependency Installation Rule

Before installing a dependency, the AI agent must inspect:

```bash
composer.json
composer.lock
```

If the package is already installed, do not install it again.

The agent should prefer Laravel-native functionality over additional packages.

---

# 20. PHPStan / Larastan

PHPStan is part of the project quality process.

The AI agent should configure it after the basic Laravel application structure exists.

The configuration should support Laravel-specific static analysis through Larastan where appropriate.

Typical verification:

```bash
vendor/bin/phpstan analyse
```

The final command and configuration must correspond to the installed versions.

---

# 21. PHPUnit

PHPUnit is the primary automated testing framework.

Verify:

```bash
php artisan test
```

The AI agent is responsible for creating the Feature and Unit tests defined in:

```text
07-TESTING-SPEC.md
```

The developer should not have to manually write the tests.

---

# 22. Laravel Pint

Pint is the project's PHP formatting tool.

Verify:

```bash
vendor/bin/pint --test
```

The AI agent should use Pint throughout implementation.

The developer should not manually reformat generated code unless reviewing stylistic issues.

---

# 23. Manual Tools for API Testing

At least one HTTP API client is recommended for manual verification.

Possible choices:

```text
Postman
Insomnia
VS Code REST Client
PhpStorm HTTP Client
curl
```

Only one is necessary.

The tool itself does not become an application dependency.

---

# 24. API Client Environment

If Postman/Insomnia or another HTTP client is used, configure a local base URL.

Example:

```text
http://127.0.0.1:8000/api/v1
```

If Laravel is started with:

```bash
php artisan serve
```

the default URL is normally:

```text
http://127.0.0.1:8000
```

Do not hardcode environment-specific URLs into application code.

---

# 25. Starting the API

The developer or AI agent can start the application with:

```bash
php artisan serve
```

Then the API can be accessed through:

```text
http://127.0.0.1:8000
```

API routes are under:

```text
/api/v1
```

---

# 26. Database Migration

During development:

```bash
php artisan migrate
```

For a clean local database:

```bash
php artisan migrate:fresh
```

With seed data:

```bash
php artisan migrate:fresh --seed
```

The last command is especially useful for manual API verification.

---

# 27. Seeder Verification

After:

```bash
php artisan migrate:fresh --seed
```

verify that the database contains enough data to demonstrate:

* doctors;
* patients;
* future availability;
* appointments;
* different appointment statuses.

The exact dataset is defined by the database and implementation specifications.

---

# 28. Manual API Verification

After seeding and starting the API, manually verify at least:

### Doctors

```http
GET /api/v1/doctors
```

### Patients

```http
GET /api/v1/patients
```

### Doctor availability / slots

Use the documented endpoint from `04-API-SPEC.md`.

### Doctor appointments

Use the documented doctor appointment listing endpoint.

### Appointment creation

Create one valid appointment using the API specification's example payload.

### Invalid appointment

Attempt an appointment that conflicts with an existing appointment.

Expected result:

```text
409 Conflict
```

---

# 29. Manual Business Rule Verification

The developer should manually verify a small number of important business rules in addition to automated tests.

At minimum:

```text
1. Valid appointment can be created.
2. Conflicting doctor appointment is rejected.
3. Conflicting patient appointment is rejected.
4. Appointment outside availability is rejected.
5. Invalid 15-minute boundary is rejected.
6. Valid status transition works.
7. Invalid status transition is rejected.
8. Cancellation boundary behaves correctly.
```

Automated tests remain the authoritative regression mechanism.

Manual testing is a final sanity check.

---

# 30. Git Milestone Workflow

The developer should understand the milestone workflow even if the AI agent executes the commands.

Recommended sequence:

```text
Create project
    ↓
Initial commit
    ↓
Database milestone
    ↓
API foundation
    ↓
Doctor/Patient CRUD
    ↓
Availability
    ↓
Appointments
    ↓
Slot generation
    ↓
Tests / edge cases
    ↓
Documentation
    ↓
Final verification
```

Each milestone must leave the repository in a working state.

---

# 31. Initial Commit

After the base project is working:

```bash
git add .
git commit -m "chore: initialize Laravel project"
```

Then push if the remote repository is already configured:

```bash
git push -u origin main
```

The actual default branch name should match the repository configuration.

---

# 32. Commit Before Major Implementation

Before implementing the first business feature, verify:

```bash
git status
php artisan test
vendor/bin/phpstan analyse
vendor/bin/pint --test
```

Then create the appropriate milestone commit.

This provides a clean rollback point.

---

# 33. Secrets

The developer must never commit:

```text
.env
credentials
API tokens
passwords
private keys
database production credentials
```

Check:

```bash
git status
```

before every milestone commit.

---

# 34. `.gitignore`

The repository must ignore at minimum:

```text
.env
/vendor/
/node_modules/
database/*.sqlite
storage/logs/*
storage/framework/cache/*
storage/framework/sessions/*
storage/framework/views/*
```

The exact Laravel-generated `.gitignore` should be retained and extended only where necessary.

---

# 35. What Must Be Manually Reviewed

Even when implementation is AI-generated, the developer must manually review:

### Business logic

* appointment conflict rules;
* availability overlap;
* status transitions;
* cancellation rule;
* slot generation.

### Security

* unexpected data exposure;
* `.env` handling;
* mass assignment;
* error leakage.

### API contract

* endpoint URLs;
* HTTP methods;
* response structures;
* validation responses.

### Code quality

* unnecessary abstractions;
* unnecessary dependencies;
* duplicated logic;
* overly complex implementation.

---

# 36. Final Manual Verification

Before submission, the developer should perform:

```bash
git status
```

Then:

```bash
php artisan migrate:fresh --seed
php artisan test
vendor/bin/phpstan analyse
vendor/bin/pint --test
```

Then start the API:

```bash
php artisan serve
```

And manually verify the documented API examples.

---

# 37. Fresh Clone Test

The final repository should be tested from a clean clone whenever practical.

Recommended:

```text
Fresh clone
    ↓
composer install
    ↓
.env setup
    ↓
SQLite setup
    ↓
php artisan key:generate
    ↓
php artisan migrate:fresh --seed
    ↓
php artisan test
    ↓
PHPStan
    ↓
Pint
    ↓
php artisan serve
    ↓
Manual API verification
```

This catches undocumented local dependencies.

---

# 38. Developer Checklist

## Before coding

* [ ] PHP installed
* [ ] Correct PHP version active
* [ ] Composer installed
* [ ] Git installed
* [ ] SQLite PHP extensions available
* [ ] Git identity configured
* [ ] Remote repository created
* [ ] Project directory selected

## Laravel setup

* [ ] Laravel project created
* [ ] `.env` created
* [ ] application key generated
* [ ] SQLite database created
* [ ] migrations run
* [ ] PHPUnit available
* [ ] PHPStan/Larastan available
* [ ] Pint available

## Manual API tools

* [ ] API client selected
* [ ] local base URL configured
* [ ] API can start
* [ ] seed data available

## During development

* [ ] milestones committed
* [ ] tests run after milestones
* [ ] PHPStan run
* [ ] Pint run
* [ ] Git status reviewed

## Before submission

* [ ] clean migration works
* [ ] seeding works
* [ ] PHPUnit passes
* [ ] PHPStan passes
* [ ] Pint passes
* [ ] API starts
* [ ] manual API checks completed
* [ ] no `.env` committed
* [ ] no SQLite database committed
* [ ] Git history is clean and understandable
* [ ] README contains setup instructions
* [ ] repository is pushed

---

# 39. Final Responsibility Split

The simplest rule is:

### Developer manually handles

```text
Machine
Accounts
GitHub repository
PHP/Composer/Git installation
PHP PATH / environment
SQLite PHP extension
Final review
Final manual API verification
Submission
```

### AI coding agent handles

```text
Laravel implementation
Migrations
Models
Factories
Seeders
API
Validation
Business logic
Resources
Tests
PHPStan configuration
Pint configuration
README
Documentation
Git milestone commands
```

### Both verify

```text
Tests
Static analysis
Formatting
API behavior
Business rules
Git state
Final project completeness
```

---

# 40. Definition of Done

The project is ready for submission only when a developer can perform the following from a clean environment:

```text
clone repository
        ↓
composer install
        ↓
configure .env
        ↓
create SQLite database
        ↓
generate application key
        ↓
migrate + seed
        ↓
run tests
        ↓
run PHPStan
        ↓
run Pint
        ↓
start API
        ↓
execute documented API examples
```

without undocumented manual fixes.

The README must contain enough information for another developer to reproduce this process.
