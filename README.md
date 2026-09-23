# Medicare Backend Test

A Laravel-based REST API for managing doctors, patients, doctor availability periods, and appointments.

## Requirements

- PHP 8.3+
- Composer
- SQLite (with PDO extension)
- Git

## Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
```

## Database Setup

The project uses SQLite. The database file is created automatically:

```bash
php artisan migrate
```

For development with seed data:

```bash
php artisan migrate:fresh --seed
```

## Running the Application

```bash
php artisan serve
```

The API will be available at: `http://127.0.0.1:8000/api/v1`

## Testing

```bash
php artisan test
```

## Static Analysis

```bash
vendor/bin/phpstan analyse
```

## Code Formatting

Check formatting:
```bash
vendor/bin/pint --test
```

Apply formatting:
```bash
vendor/bin/pint
```

## API Endpoints

All endpoints are versioned under `/api/v1`:

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/doctors` | List doctors |
| POST | `/api/v1/doctors` | Create doctor |
| GET | `/api/v1/doctors/{doctor}` | Get doctor |
| PATCH | `/api/v1/doctors/{doctor}` | Update doctor |
| DELETE | `/api/v1/doctors/{doctor}` | Soft-delete doctor |
| GET | `/api/v1/doctors/{doctor}/appointments` | List doctor's appointments |
| GET | `/api/v1/doctors/{doctor}/available-slots` | List available slots |
| GET | `/api/v1/patients` | List patients |
| POST | `/api/v1/patients` | Create patient |
| GET | `/api/v1/patients/{patient}` | Get patient |
| PATCH | `/api/v1/patients/{patient}` | Update patient |
| DELETE | `/api/v1/patients/{patient}` | Soft-delete patient |
| GET | `/api/v1/patients/{patient}/appointments` | List patient's appointments |
| GET | `/api/v1/availabilities` | List availabilities |
| POST | `/api/v1/availabilities` | Create availability |
| GET | `/api/v1/availabilities/{availability}` | Get availability |
| PATCH | `/api/v1/availabilities/{availability}` | Update availability |
| DELETE | `/api/v1/availabilities/{availability}` | Soft-delete availability |
| GET | `/api/v1/appointments` | List appointments |
| POST | `/api/v1/appointments` | Create appointment |
| GET | `/api/v1/appointments/{appointment}` | Get appointment |
| PATCH | `/api/v1/appointments/{appointment}` | Update appointment/status |

### Pagination

All collection endpoints support:
- `page` (default: 1)
- `per_page` (default: 25, max: 100)

Example:
```
GET /api/v1/doctors?page=2&per_page=25
```

## Design Decisions

- **SQLite** — Single-file database for simplicity
- **UTC** — Internal timezone for all date/time handling
- **Soft Deletes** — All entities use soft deletes
- **Service Layer** — Business logic in services (AvailabilityService, SlotService, AppointmentService)
- **API Resources** — Response serialization via Laravel API Resources
- **Form Requests** — HTTP validation in dedicated request classes
- **/api/v1** — API versioning from the start
- **No Authentication** — Out of scope for this test
- **No Repository Pattern** — Direct Eloquent usage in services