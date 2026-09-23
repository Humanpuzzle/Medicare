# Medicare Backend Test — API Specification

## 1. Purpose

This document defines the public REST API contract of the Medicare Backend Test application.

It specifies:

* API versioning
* endpoint structure
* HTTP methods
* request formats
* response formats
* validation behavior
* business-rule errors
* pagination
* filtering
* appointment lifecycle operations
* available-slot calculation
* HTTP status codes

The API is backend-only and returns JSON responses.

---

# 2. API Conventions

## 2.1 Base URL

All endpoints are versioned under:

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

---

## 2.2 Content Type

Requests containing a body must use:

```http
Content-Type: application/json
```

Successful responses use:

```http
Content-Type: application/json
```

---

## 2.3 Authentication

The API does not require authentication or authorization.

All endpoints are publicly accessible within the scope of the coding test.

---

## 2.4 Date and Time Format

The API uses ISO-8601 date/time strings.

Example:

```text
2026-10-15T09:15:00Z
```

The application uses UTC for internal date/time handling.

Business rules involving availability and appointment times are interpreted according to the application's configured business timezone (`Europe/Budapest`).

Clients should send and receive timestamps using explicit timezone information.

---

# 3. HTTP Status Codes

The API uses conventional HTTP status codes.

| Status                      | Meaning                            |
| --------------------------- | ---------------------------------- |
| `200 OK`                    | Successful read or update          |
| `201 Created`               | Resource successfully created      |
| `204 No Content`            | Resource successfully deleted      |
| `400 Bad Request`           | Invalid request/business operation |
| `404 Not Found`             | Requested resource does not exist  |
| `409 Conflict`              | Resource/business conflict         |
| `422 Unprocessable Entity`  | Validation failure                 |
| `500 Internal Server Error` | Unexpected server-side error       |

The implementation should prefer `422` for validation failures and `409` for validly structured requests that violate a domain conflict, such as an appointment collision.

---

# 4. Standard Error Response

Errors must return a predictable JSON structure.

## 4.1 General Error

```json
{
    "message": "Appointment conflicts with an existing appointment."
}
```

---

## 4.2 Validation Error

Validation errors use a structured `errors` object.

Example:

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "name": [
            "The name field is required."
        ],
        "email": [
            "The email must be a valid email address."
        ]
    }
}
```

HTTP status:

```text
422 Unprocessable Entity
```

---

## 4.3 Business Rule Error

Example:

```json
{
    "message": "The appointment must be completely contained within a single availability period."
}
```

Business-rule errors must not be represented as successful responses.

---

# 5. Pagination

All collection endpoints that can return multiple records are paginated.

## 5.1 Query Parameters

```text
?page=1&per_page=25
```

Parameters:

| Parameter  | Default | Maximum |
| ---------- | ------: | ------: |
| `page`     |     `1` |       - |
| `per_page` |    `25` |   `100` |

If `per_page` exceeds `100`, the API must reject the request with a validation error.

---

## 5.2 Pagination Response

Paginated responses use Laravel's standard pagination structure.

Example:

```json
{
    "data": [
        {
            "id": 1,
            "name": "Dr. John Smith",
            "email": "john@example.com",
            "specialty": "Cardiology"
        }
    ],
    "links": {
        "first": "/api/v1/doctors?page=1",
        "last": "/api/v1/doctors?page=1",
        "prev": null,
        "next": null
    },
    "meta": {
        "current_page": 1,
        "from": 1,
        "last_page": 1,
        "per_page": 25,
        "to": 1,
        "total": 1
    }
}
```

The exact generated pagination links may contain the complete application URL depending on Laravel configuration.

---

## 5.3 Ordering

Collection endpoints must use deterministic backend-controlled ordering.

Clients must not be able to produce nondeterministic result ordering.

Unless an endpoint explicitly defines another ordering, records are ordered by:

```text
id ASC
```

Date-oriented endpoints may define a domain-specific ordering where appropriate.

---

# 6. Doctors

## 6.1 List Doctors

```http
GET /api/v1/doctors
```

Returns a paginated list of doctors.

### Query Parameters

```text
?page=1
&per_page=25
```

### Response

```http
200 OK
```

```json
{
    "data": [
        {
            "id": 1,
            "name": "Dr. John Smith",
            "email": "john@example.com",
            "specialty": "Cardiology",
            "created_at": "2026-09-23T10:00:00Z",
            "updated_at": "2026-09-23T10:00:00Z"
        }
    ],
    "links": {},
    "meta": {}
}
```

---

# 6.2 Get Doctor

```http
GET /api/v1/doctors/{doctor}
```

Returns one doctor.

### Response

```http
200 OK
```

```json
{
    "data": {
        "id": 1,
        "name": "Dr. John Smith",
        "email": "john@example.com",
        "specialty": "Cardiology",
        "created_at": "2026-09-23T10:00:00Z",
        "updated_at": "2026-09-23T10:00:00Z"
    }
}
```

If the doctor does not exist:

```http
404 Not Found
```

---

# 6.3 Create Doctor

```http
POST /api/v1/doctors
```

### Request

```json
{
    "name": "Dr. John Smith",
    "email": "john@example.com",
    "specialty": "Cardiology"
}
```

### Validation

Required:

* `name`
* `email`
* `specialty`

`email` must:

* be a valid email address
* be normalized to lowercase
* be unique case-insensitively

For example:

```text
John@Example.com
john@example.com
```

must be treated as the same email address.

### Response

```http
201 Created
```

```json
{
    "data": {
        "id": 1,
        "name": "Dr. John Smith",
        "email": "john@example.com",
        "specialty": "Cardiology",
        "created_at": "2026-09-23T10:00:00Z",
        "updated_at": "2026-09-23T10:00:00Z"
    }
}
```

---

# 6.4 Update Doctor

```http
PATCH /api/v1/doctors/{doctor}
```

Partial updates are supported.

Example:

```json
{
    "specialty": "Neurology"
}
```

### Response

```http
200 OK
```

```json
{
    "data": {
        "id": 1,
        "name": "Dr. John Smith",
        "email": "john@example.com",
        "specialty": "Neurology",
        "created_at": "2026-09-23T10:00:00Z",
        "updated_at": "2026-09-23T10:15:00Z"
    }
}
```

Email normalization and uniqueness rules remain applicable.

---

# 6.5 Delete Doctor

```http
DELETE /api/v1/doctors/{doctor}
```

Doctors use soft deletion.

### Response

```http
204 No Content
```

A soft-deleted doctor must not appear in normal API queries.

Existing historical records remain associated with the deleted doctor.

---

# 7. Patients

## 7.1 List Patients

```http
GET /api/v1/patients
```

### Query Parameters

```text
?page=1
&per_page=25
```

### Response

```http
200 OK
```

```json
{
    "data": [
        {
            "id": 1,
            "name": "Jane Doe",
            "email": "jane@example.com",
            "phone": "+3612345678",
            "created_at": "2026-09-23T10:00:00Z",
            "updated_at": "2026-09-23T10:00:00Z"
        }
    ],
    "links": {},
    "meta": {}
}
```

---

# 7.2 Get Patient

```http
GET /api/v1/patients/{patient}
```

Returns one patient.

---

# 7.3 Create Patient

```http
POST /api/v1/patients
```

### Request

```json
{
    "name": "Jane Doe",
    "email": "jane@example.com",
    "phone": "+3612345678"
}
```

### Validation

Required:

* `name`
* `email`
* `phone`

Email uniqueness is case-insensitive and emails are normalized to lowercase before persistence.

### Response

```http
201 Created
```

---

# 7.4 Update Patient

```http
PATCH /api/v1/patients/{patient}
```

Partial updates are supported.

Example:

```json
{
    "phone": "+3611122334"
}
```

### Response

```http
200 OK
```

---

# 7.5 Delete Patient

```http
DELETE /api/v1/patients/{patient}
```

Patients use soft deletion.

### Response

```http
204 No Content
```

Historical appointments remain associated with the deleted patient.

---

# 8. Doctor Appointments

A doctor's appointments can be queried through a nested endpoint.

```http
GET /api/v1/doctors/{doctor}/appointments
```

This endpoint returns appointments belonging to the specified doctor.

### Query Parameters

```text
?page=1
&per_page=25
&status=pending
```

Supported status values:

```text
pending
confirmed
completed
cancelled
```

### Example

```http
GET /api/v1/doctors/1/appointments?status=confirmed
```

### Response

```http
200 OK
```

```json
{
    "data": [
        {
            "id": 15,
            "patient_id": 4,
            "doctor_id": 1,
            "start_time": "2026-10-15T09:00:00Z",
            "end_time": "2026-10-15T09:30:00Z",
            "status": "confirmed",
            "cancellation_reason": null,
            "created_at": "2026-09-23T10:00:00Z",
            "updated_at": "2026-09-23T10:00:00Z"
        }
    ],
    "links": {},
    "meta": {}
}
```

---

# 9. Patient Appointments

A patient's appointments can be queried through:

```http
GET /api/v1/patients/{patient}/appointments
```

### Query Parameters

```text
?page=1
&per_page=25
&status=confirmed
```

The same appointment status values are supported.

### Response

The endpoint returns a paginated appointment collection.

---

# 10. Availabilities

## 10.1 List Availabilities

```http
GET /api/v1/availabilities
```

### Query Parameters

```text
?page=1
&per_page=25
&doctor_id=1
```

`doctor_id` may be used to filter availability periods belonging to a specific doctor.

### Response

```json
{
    "data": [
        {
            "id": 1,
            "doctor_id": 1,
            "starts_at": "2026-10-15T09:00:00Z",
            "ends_at": "2026-10-15T11:00:00Z",
            "slot_duration": 30,
            "created_at": "2026-09-23T10:00:00Z",
            "updated_at": "2026-09-23T10:00:00Z"
        }
    ],
    "links": {},
    "meta": {}
}
```

`slot_duration` is expressed in minutes.

---

# 10.2 Get Availability

```http
GET /api/v1/availabilities/{availability}
```

Returns one availability period.

---

# 10.3 Create Availability

```http
POST /api/v1/availabilities
```

### Request

```json
{
    "doctor_id": 1,
    "starts_at": "2026-10-15T09:00:00Z",
    "ends_at": "2026-10-15T11:00:00Z",
    "slot_duration": 30
}
```

### Validation

The request must satisfy:

* doctor exists
* `starts_at < ends_at`
* start is in the future
* availability duration is at least 30 minutes
* `slot_duration` is valid
* the new availability does not overlap another availability belonging to the same doctor

Adjacent availability periods are allowed.

Example:

```text
09:00–10:00
10:00–11:00
```

is valid.

The following is invalid:

```text
09:00–10:00
09:30–11:00
```

### Response

```http
201 Created
```

---

# 10.4 Update Availability

```http
PATCH /api/v1/availabilities/{availability}
```

Example:

```json
{
    "starts_at": "2026-10-15T10:00:00Z",
    "ends_at": "2026-10-15T12:00:00Z"
}
```

All availability business rules are re-evaluated after the update.

### Response

```http
200 OK
```

---

# 10.5 Delete Availability

```http
DELETE /api/v1/availabilities/{availability}
```

Availability records use soft deletion.

### Response

```http
204 No Content
```

Deleting an availability does not delete historical appointments.

---

# 11. Available Slots

Available slots are generated from a doctor's availability periods and existing appointments.

## 11.1 Endpoint

```http
GET /api/v1/doctors/{doctor}/available-slots
```

### Query Parameters

The endpoint supports date/range filtering.

Example:

```text
?date=2026-10-15
```

or:

```text
?from=2026-10-15
&to=2026-10-20
```

Pagination may be applied to the generated slot collection where required by the implementation.

---

## 11.2 Slot Generation

Given:

```text
Availability:
09:00–11:00

slot_duration:
30 minutes
```

the generated base slots are:

```text
09:00–09:30
09:30–10:00
10:00–10:30
10:30–11:00
```

Existing conflicting appointments must be taken into account.

---

## 11.3 Appointment Start Grid

Appointment starts use a 15-minute grid:

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

An appointment does not have to start exactly on a `slot_duration` boundary.

For example, with:

```text
slot_duration = 30
```

the following start time is valid:

```text
09:15
```

provided that all other appointment rules are satisfied.

---

## 11.4 Appointment Duration

Every appointment must be at least 30 minutes.

The appointment duration must be an integer multiple of the availability's `slot_duration`.

Example:

```text
slot_duration = 30

30 minutes  -> valid
60 minutes  -> valid
90 minutes  -> valid
45 minutes  -> invalid
```

The appointment must remain completely inside one availability period.

---

# 12. Appointments

## 12.1 List Appointments

```http
GET /api/v1/appointments
```

### Query Parameters

```text
?page=1
&per_page=25
&doctor_id=1
&patient_id=5
&status=confirmed
```

Supported filters:

* `doctor_id`
* `patient_id`
* `status`

Filters may be combined.

Example:

```http
GET /api/v1/appointments?doctor_id=1&status=confirmed
```

---

# 12.2 Get Appointment

```http
GET /api/v1/appointments/{appointment}
```

### Response

```json
{
    "data": {
        "id": 15,
        "patient_id": 4,
        "doctor_id": 1,
        "start_time": "2026-10-15T09:00:00Z",
        "end_time": "2026-10-15T09:30:00Z",
        "status": "pending",
        "cancellation_reason": null,
        "created_at": "2026-09-23T10:00:00Z",
        "updated_at": "2026-09-23T10:00:00Z"
    }
}
```

---

# 12.3 Create Appointment

```http
POST /api/v1/appointments
```

### Request

```json
{
    "patient_id": 4,
    "doctor_id": 1,
    "start_time": "2026-10-15T09:15:00Z",
    "end_time": "2026-10-15T09:45:00Z"
}
```

The initial status is:

```text
pending
```

The client does not need to provide the initial status.

### Validation and Business Rules

The appointment creation operation must verify:

1. patient exists
2. doctor exists
3. start time is in the future
4. `start_time < end_time`
5. appointment duration is at least 30 minutes
6. start time is on the 15-minute grid
7. appointment is completely contained within one availability
8. appointment duration is a valid multiple of the availability's `slot_duration`
9. doctor has no conflicting active appointment
10. patient has no conflicting active appointment

---

## 12.4 Appointment Conflict

Appointment intervals are interpreted as:

```text
[start_time, end_time)
```

Two appointments conflict when:

```text
existing.start_time < new.end_time
AND
existing.end_time > new.start_time
```

Therefore:

```text
09:00–09:30
09:30–10:00
```

do not conflict.

However:

```text
09:00–09:30
09:15–09:45
```

do conflict.

---

## 12.5 Blocking Appointment States

The following appointment states block new appointments:

```text
pending
confirmed
```

The following states do not block new appointments:

```text
completed
cancelled
```

---

# 13. Appointment Status Updates

Appointments use the following status values:

```text
pending
confirmed
completed
cancelled
```

The allowed transitions are:

```text
pending → confirmed
pending → cancelled

confirmed → completed
confirmed → cancelled
```

Terminal states:

```text
completed
cancelled
```

A terminal appointment cannot transition to another state.

---

## 13.1 Update Appointment

```http
PATCH /api/v1/appointments/{appointment}
```

The endpoint is used for supported appointment modifications and state transitions.

Example:

```json
{
    "status": "confirmed"
}
```

### Response

```http
200 OK
```

---

# 14. Appointment Cancellation

Cancellation is represented by:

```json
{
    "status": "cancelled",
    "cancellation_reason": "Patient requested cancellation."
}
```

`cancellation_reason` is optional and nullable.

Example without a reason:

```json
{
    "status": "cancelled"
}
```

---

## 14.1 Cancellation Rule

A `confirmed` appointment may be cancelled only when its start time is at least 24 hours in the future.

The boundary is inclusive.

Therefore:

```text
24 hours or more     -> allowed
less than 24 hours   -> rejected
```

The rule applies only to:

```text
confirmed → cancelled
```

---

# 15. Appointment State Transition Errors

Attempting an invalid transition must result in a business-rule error.

Example:

```http
PATCH /api/v1/appointments/15
```

```json
{
    "status": "completed"
}
```

when the current state is:

```text
pending
```

must be rejected.

Example response:

```http
409 Conflict
```

```json
{
    "message": "The appointment cannot transition from pending to completed."
}
```

---

# 16. Appointment Creation Error Examples

## 16.1 Unknown Patient

```json
{
    "message": "The selected patient does not exist."
}
```

---

## 16.2 Unknown Doctor

```json
{
    "message": "The selected doctor does not exist."
}
```

---

## 16.3 Appointment in the Past

```json
{
    "message": "The appointment must be scheduled in the future."
}
```

---

## 16.4 Outside Availability

```json
{
    "message": "The appointment must be completely contained within a single availability period."
}
```

---

## 16.5 Invalid Start Grid

```json
{
    "message": "The appointment start time must be on a 15-minute grid."
}
```

---

## 16.6 Invalid Duration

```json
{
    "message": "The appointment duration must be at least 30 minutes and must be a multiple of the availability slot duration."
}
```

---

## 16.7 Doctor Conflict

```json
{
    "message": "The doctor already has an appointment during the requested time."
}
```

---

## 16.8 Patient Conflict

```json
{
    "message": "The patient already has an appointment during the requested time."
}
```

---

# 17. Soft Deleted Resources

Doctors, patients and availability records use soft deletion.

Normal API queries must exclude soft-deleted records.

Historical appointments are not deleted automatically because of the deletion of their related doctor or patient.

The API does not expose soft-deleted records through normal resource endpoints.

---

# 18. Resource Serialization

Laravel API Resources are used to serialize API responses.

The following resources are expected:

```text
DoctorResource
PatientResource
AvailabilityResource
AppointmentResource
AvailableSlotResource
```

Resources are responsible for response serialization only.

Business logic must not be implemented inside API Resources.

---

# 19. Relationship Serialization

By default, resources should return identifiers for relationships:

```json
{
    "patient_id": 4,
    "doctor_id": 1
}
```

The API does not require returning complete nested doctor/patient objects in every appointment response.

This keeps payloads predictable and avoids unnecessary database queries.

---

# 20. Route Model Binding

Laravel route model binding may be used for resource endpoints.

Example:

```text
GET /api/v1/doctors/{doctor}
```

If the requested resource does not exist, the API returns:

```http
404 Not Found
```

Soft-deleted resources are excluded from normal route model binding.

---

# 21. Filtering Rules

Filtering must be performed server-side.

Supported filters include:

### Appointments

```text
doctor_id
patient_id
status
```

### Availabilities

```text
doctor_id
```

Additional date filtering is available for available-slot queries.

Unknown filter parameters should not silently change application behavior.

---

# 22. API Consistency Rules

The following conventions apply to all endpoints:

* JSON request/response format
* `/api/v1` version prefix
* REST-oriented resource naming
* plural resource names
* `GET` for reads
* `POST` for creation
* `PATCH` for partial updates
* `DELETE` for deletion
* paginated collections
* deterministic ordering
* Laravel API Resources for serialization
* validation through Form Requests
* domain/business rules through application services
* no authentication layer
* no frontend-specific response formats

---

# 23. Endpoint Summary

| Method   | Endpoint                                   | Purpose                     |
| -------- | ------------------------------------------ | --------------------------- |
| `GET`    | `/api/v1/doctors`                          | List doctors                |
| `POST`   | `/api/v1/doctors`                          | Create doctor               |
| `GET`    | `/api/v1/doctors/{doctor}`                 | Get doctor                  |
| `PATCH`  | `/api/v1/doctors/{doctor}`                 | Update doctor               |
| `DELETE` | `/api/v1/doctors/{doctor}`                 | Soft-delete doctor          |
| `GET`    | `/api/v1/doctors/{doctor}/appointments`    | List doctor's appointments  |
| `GET`    | `/api/v1/doctors/{doctor}/available-slots` | List available slots        |
| `GET`    | `/api/v1/patients`                         | List patients               |
| `POST`   | `/api/v1/patients`                         | Create patient              |
| `GET`    | `/api/v1/patients/{patient}`               | Get patient                 |
| `PATCH`  | `/api/v1/patients/{patient}`               | Update patient              |
| `DELETE` | `/api/v1/patients/{patient}`               | Soft-delete patient         |
| `GET`    | `/api/v1/patients/{patient}/appointments`  | List patient's appointments |
| `GET`    | `/api/v1/availabilities`                   | List availabilities         |
| `POST`   | `/api/v1/availabilities`                   | Create availability         |
| `GET`    | `/api/v1/availabilities/{availability}`    | Get availability            |
| `PATCH`  | `/api/v1/availabilities/{availability}`    | Update availability         |
| `DELETE` | `/api/v1/availabilities/{availability}`    | Soft-delete availability    |
| `GET`    | `/api/v1/appointments`                     | List appointments           |
| `POST`   | `/api/v1/appointments`                     | Create appointment          |
| `GET`    | `/api/v1/appointments/{appointment}`       | Get appointment             |
| `PATCH`  | `/api/v1/appointments/{appointment}`       | Update appointment/status   |

---

# 24. API Scope

The API intentionally does not include:

* authentication
* authorization
* user registration/login
* password management
* frontend-specific endpoints
* webhooks
* notifications
* asynchronous job endpoints
* external integrations
* calendar synchronization
* payment functionality

The API remains focused on the requirements of the backend coding test.
