# PolicyFlow API Documentation

## Overview

PolicyFlow is a Fault-Tolerant Insurance Issuance & Reconciliation Engine built with Laravel 11. This document provides comprehensive API documentation for all endpoints.

**Base URL**: `http://localhost:8000/api` (development)

**Content-Type**: `application/json`

---

## Authentication

Currently, the API is open (no authentication required). For production deployment, implement authentication (Sanctum, Passport, or JWT).

---

## Error Responses

All error responses follow a consistent format:

### 422 Validation Error
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "field_name": ["Error message for field"]
  }
}
```

### 404 Not Found
```json
{
  "message": "Policy request not found"
}
```

### 422 Invalid State Transition
```json
{
  "message": "Invalid state transition",
  "current_status": "ISSUED",
  "allowed_transitions": []
}
```

---

## Policy Requests

### Create Policy Request

**Endpoint**: `POST /api/policy-requests`

**Description**: Creates a new policy request or returns existing one if duplicate (same `agent_id` + `client_request_id`).

**Request Body**:
```json
{
  "client_request_id": "AGT-2026-0001",
  "agent_id": "AGENT-17",
  "customer_name": "Sample Customer",
  "date_of_birth": "1998-04-10",
  "destination": "INTERNATIONAL",
  "trip_start": "2026-11-10",
  "trip_end": "2026-11-14",
  "plan_code": "PLUS",
  "sum_insured": 300000
}
```

**Field Validation Rules**:

| Field | Required | Type | Validation |
|-------|----------|------|------------|
| client_request_id | Yes | string | max:50, unique with agent_id |
| agent_id | Yes | string | max:50 |
| customer_name | Yes | string | max:255 |
| date_of_birth | Yes | date | before:trip_start, before:today |
| destination | Yes | string | in:DOMESTIC,INTERNATIONAL |
| trip_start | Yes | date | after:today |
| trip_end | Yes | date | after_or_equal:trip_start |
| plan_code | Yes | string | in:BASIC,PLUS,PREMIUM |
| sum_insured | Yes | numeric | between:1000,10000000 |

**Success Response (201 - New Request)**:
```json
{
  "message": "Policy request created successfully",
  "data": {
    "id": 1,
    "client_request_id": "AGT-2026-0001",
    "agent_id": "AGENT-17",
    "customer_name": "Sample Customer",
    "date_of_birth": "1998-04-10",
    "destination": "INTERNATIONAL",
    "trip_start": "2026-11-10",
    "trip_end": "2026-11-14",
    "plan_code": "PLUS",
    "sum_insured": "300000.00",
    "travel_days": 5,
    "age_at_travel": 28,
    "premium_breakdown": {
      "base_daily_rate": "20.00",
      "age_multiplier": "1.00",
      "destination_multiplier": "1.80",
      "coverage_multiplier": "1.25"
    },
    "calculated_premium": "225.00",
    "status": "VALIDATED",
    "provider_reference": null,
    "policy_number": null,
    "attempt_count": 0,
    "last_response": null,
    "created_at": "2026-10-23T10:00:00.000000Z",
    "updated_at": "2026-10-23T10:00:00.000000Z",
    "audit_trail": [
      {
        "from_state": null,
        "to_state": "RECEIVED",
        "metadata": { "created_via": "api" },
        "created_at": "2026-10-23T10:00:00.000000Z"
      },
      {
        "from_state": "RECEIVED",
        "to_state": "VALIDATED",
        "metadata": { "reason": "All validations passed", "premium_calculated": 225.00 },
        "created_at": "2026-10-23T10:00:00.000000Z"
      }
    ]
  }
}
```

**Success Response (200 - Duplicate Request)**:
```json
{
  "message": "Policy request already exists",
  "data": { ... }  // Same structure as above with existing request data
}
```

**Error Response (422)**:
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "trip_end": ["Trip end date must be on or after trip start date."],
    "plan_code": ["Unsupported plan code: INVALID_PLAN"]
  }
}
```

---

### List Policy Requests

**Endpoint**: `GET /api/policy-requests`

**Description**: Lists all policy requests with pagination and optional filters.

**Query Parameters**:

| Parameter | Type | Description |
|-----------|------|-------------|
| status | string | Filter by status (RECEIVED, VALIDATED, SUBMITTED, PENDING_CONFIRMATION, ISSUED, FAILED, NEEDS_RECONCILIATION) |
| agent_id | string | Filter by agent ID |
| date_from | date | Filter requests created on or after this date (YYYY-MM-DD) |
| date_to | date | Filter requests created on or before this date (YYYY-MM-DD) |
| per_page | integer | Results per page (default: 15, max: 100) |

**Example**: `GET /api/policy-requests?status=VALIDATED&agent_id=AGENT-17&per_page=20`

**Success Response (200)**:
```json
{
  "data": [
    {
      "id": 1,
      "client_request_id": "AGT-2026-0001",
      "agent_id": "AGENT-17",
      "customer_name": "Sample Customer",
      "date_of_birth": "1998-04-10",
      "destination": "INTERNATIONAL",
      "trip_start": "2026-11-10",
      "trip_end": "2026-11-14",
      "plan_code": "PLUS",
      "sum_insured": "300000.00",
      "travel_days": 5,
      "age_at_travel": 28,
      "premium_breakdown": {
        "base_daily_rate": "20.00",
        "age_multiplier": "1.00",
        "destination_multiplier": "1.80",
        "coverage_multiplier": "1.25"
      },
      "calculated_premium": "225.00",
      "status": "VALIDATED",
      "provider_reference": null,
      "policy_number": null,
      "attempt_count": 0,
      "last_response": null,
      "created_at": "2026-10-23T10:00:00.000000Z",
      "updated_at": "2026-10-23T10:00:00.000000Z",
      "audit_trail": [...]
    }
  ],
  "meta": {
    "total": 25,
    "per_page": 15,
    "current_page": 1,
    "last_page": 2
  }
}
```

---

### Get Policy Request

**Endpoint**: `GET /api/policy-requests/{id}`

**Description**: Retrieves a single policy request with full audit trail, issuance attempts, and provider events.

**Path Parameters**:
| Parameter | Type | Description |
|-----------|------|-------------|
| id | integer | Policy request ID |

**Success Response (200)**:
```json
{
  "data": {
    "id": 1,
    "client_request_id": "AGT-2026-0001",
    "agent_id": "AGENT-17",
    "customer_name": "Sample Customer",
    "date_of_birth": "1998-04-10",
    "destination": "INTERNATIONAL",
    "trip_start": "2026-11-10",
    "trip_end": "2026-11-14",
    "plan_code": "PLUS",
    "sum_insured": "300000.00",
    "travel_days": 5,
    "age_at_travel": 28,
    "premium_breakdown": { ... },
    "calculated_premium": "225.00",
    "status": "ISSUED",
    "provider_reference": "PROV-20261023100000-ABC12345",
    "policy_number": "POL-20261023-100000-ABC12345",
    "attempt_count": 1,
    "last_response": {
      "scenario": "success",
      "config": { ... },
      "submitted_at": "2026-10-23T10:00:05.000000Z"
    },
    "created_at": "2026-10-23T10:00:00.000000Z",
    "updated_at": "2026-10-23T10:00:05.000000Z",
    "audit_trail": [...],
    "issuance_attempts": [
      {
        "id": 1,
        "attempt_number": 1,
        "request_payload": { ... },
        "response_payload": { ... },
        "response_status": "SUBMITTED",
        "started_at": "2026-10-23T10:00:05.000000Z",
        "completed_at": "2026-10-23T10:00:05.000000Z",
        "created_at": "2026-10-23T10:00:05.000000Z"
      }
    ],
    "provider_events": [
      {
        "id": 1,
        "provider_event_id": "EVT-PROV-20261023100000-ABC12345-ISSUED",
        "event_type": "ISSUED",
        "payload": { ... },
        "processed_at": "2026-10-23T10:00:05.000000Z",
        "created_at": "2026-10-23T10:00:05.000000Z"
      }
    ]
  }
}
```

**Error Response (404)**:
```json
{
  "message": "Policy request not found"
}
```

---

### Submit Policy Request to Insurer

**Endpoint**: `POST /api/policy-requests/{id}/submit`

**Description**: Submits a validated policy request to the mock insurer. The request must be in `VALIDATED` state.

**Path Parameters**:
| Parameter | Type | Description |
|-----------|------|-------------|
| id | integer | Policy request ID |

**Headers**:
| Header | Required | Description |
|--------|----------|-------------|
| X-Mock-Scenario | No | Mock insurer scenario: `success`, `delayed`, `failure`, `duplicate`, `timeout` (default: `success`) |

**Success Response (200)**:
```json
{
  "message": "Request submitted to insurer",
  "data": {
    "id": 1,
    "client_request_id": "AGT-2026-0001",
    "agent_id": "AGENT-17",
    "customer_name": "Sample Customer",
    "date_of_birth": "1998-04-10",
    "destination": "INTERNATIONAL",
    "trip_start": "2026-11-10",
    "trip_end": "2026-11-14",
    "plan_code": "PLUS",
    "sum_insured": "300000.00",
    "travel_days": 5,
    "age_at_travel": 28,
    "premium_breakdown": { ... },
    "calculated_premium": "225.00",
    "status": "ISSUED",
    "provider_reference": "PROV-20261023100000-ABC12345",
    "policy_number": "POL-20261023-100000-ABC12345",
    "attempt_count": 1,
    "last_response": { ... },
    "created_at": "2026-10-23T10:00:00.000000Z",
    "updated_at": "2026-10-23T10:00:05.000000Z",
    "audit_trail": [...]
  },
  "provider_response": {
    "provider_reference": "PROV-20261023100000-ABC12345",
    "status": "SUBMITTED",
    "scenario": "success",
    "message": "Immediate successful issuance"
  }
}
```

**Error Response (422 - Invalid State)**:
```json
{
  "message": "Invalid state transition",
  "current_status": "ISSUED",
  "allowed_transitions": []
}
```

---

## Mock Insurer

### Get Available Scenarios

**Endpoint**: `GET /api/mock-insurer/scenarios`

**Description**: Lists all available mock insurer scenarios with descriptions.

**Success Response (200)**:
```json
{
  "scenarios": {
    "success": {
      "description": "Immediate successful issuance",
      "delay": 0,
      "result": "ISSUED",
      "policy_number": true
    },
    "delayed": {
      "description": "Delayed callback (simulates timeout)",
      "delay": 2,
      "result": "ISSUED",
      "policy_number": true
    },
    "failure": {
      "description": "Rejected by insurer",
      "delay": 0,
      "result": "REJECTED",
      "policy_number": false
    },
    "duplicate": {
      "description": "Sends duplicate ISSUED callback",
      "delay": 0,
      "result": "ISSUED",
      "policy_number": true,
      "duplicate_callback": true
    },
    "timeout": {
      "description": "No callback (requires reconciliation)",
      "delay": 0,
      "result": "TIMEOUT",
      "policy_number": false
    }
  }
}
```

---

### Insurer Callback Endpoint

**Endpoint**: `POST /api/mock-insurer/callback`

**Description**: Receives asynchronous callbacks from the insurer. This endpoint is called by the insurer (simulated in tests) to notify about policy issuance status.

**Request Body**:
```json
{
  "provider_reference": "PROV-20261023100000-ABC12345",
  "event_type": "ISSUED",
  "policy_number": "POL-20261023-100000-ABC12345",
  "event_id": "EVT-PROV-20261023100000-ABC12345-ISSUED"
}
```

**Field Validation**:

| Field | Required | Type | Validation |
|-------|----------|------|------------|
| provider_reference | Yes | string | exists in policy_requests |
| event_type | Yes | string | in:ISSUED,REJECTED,TIMEOUT |
| policy_number | No | string | Required for ISSUED |
| event_id | No | string | Idempotency key (auto-generated if not provided) |

**Success Response (200)**:
```json
{
  "message": "Callback processed successfully",
  "event_id": "EVT-PROV-20261023100000-ABC12345-ISSUED",
  "policy_number": "POL-20261023-100000-ABC12345",
  "status": "ISSUED"
}
```

**Duplicate Callback Response (200)**:
```json
{
  "message": "Duplicate callback - already processed",
  "event_id": "EVT-PROV-20261023100000-ABC12345-ISSUED",
  "policy_number": "POL-20261023-100000-ABC12345",
  "status": "ISSUED"
}
```

**Error Response (404)**:
```json
{
  "message": "Policy request not found for provider reference"
}
```

---

### Provider Status Lookup

**Endpoint**: `GET /api/mock-insurer/status/{providerReference}`

**Description**: Checks the current status of a request with the provider. Used for reconciliation.

**Path Parameters**:
| Parameter | Type | Description |
|-----------|------|-------------|
| providerReference | string | Provider reference from submission |

**Success Response (200)**:
```json
{
  "found": true,
  "provider_reference": "PROV-20261023100000-ABC12345",
  "policy_number": "POL-20261023-100000-ABC12345",
  "status": "ISSUED",
  "provider_status": "ISSUED",
  "scenario": "success"
}
```

**Error Response (404)**:
```json
{
  "found": false,
  "status": "UNKNOWN",
  "message": "No request found for this provider reference"
}
```

---

### Test Duplicate Callback

**Endpoint**: `POST /api/mock-insurer/test/duplicate-callback`

**Description**: Simulates duplicate callback for testing idempotency.

**Request Body**:
```json
{
  "policy_request_id": 1
}
```

**Success Response (200)**:
```json
{
  "first_callback": { ... },
  "second_callback": { ... },
  "policy_number": "POL-20261023-100000-ABC12345",
  "status": "ISSUED"
}
```

---

## Admin

### Manual Reconciliation

**Endpoint**: `POST /api/admin/reconcile`

**Description**: Triggers reconciliation for stale requests or a specific provider reference.

**Request Body** (Specific Request):
```json
{
  "provider_reference": "PROV-20261023100000-ABC12345"
}
```

**Request Body** (Bulk Reconciliation):
```json
{
  "stale_minutes": 30
}
```

**Field Validation**:

| Field | Required | Type | Validation |
|-------|----------|------|------------|
| provider_reference | No* | string | If provided, force reconciles specific request |
| stale_minutes | No | integer | min:1, max:1440 (default: 30) |

*Either `provider_reference` or `stale_minutes` must be provided.

**Specific Reconciliation Response (200)**:
```json
{
  "success": true,
  "message": "Reconciled: Provider shows ISSUED",
  "action": "updated_to_issued",
  "result": {
    "event_id": "EVT-PROV-20261023100000-ABC12345-ISSUED",
    "event_type": "ISSUED",
    "policy_number": "POL-20261023-100000-ABC12345",
    "status": "ISSUED"
  }
}
```

**Bulk Reconciliation Response (200)**:
```json
{
  "total_checked": 3,
  "results": [
    {
      "policy_request_id": 1,
      "client_request_id": "AGT-2026-0001",
      "provider_reference": "PROV-20261023100000-ABC12345",
      "reconciliation": {
        "success": true,
        "message": "Reconciled: Provider timeout confirmed",
        "action": "marked_needs_reconciliation"
      }
    }
  ]
}
```

**Unknown Provider Reference Response (200)**:
```json
{
  "success": false,
  "message": "No request found for provider reference"
}
```

---

## Premium Calculation Reference

### Formula
```
premium = base_daily_rate × travel_days × age_multiplier × destination_multiplier × coverage_multiplier
```

### Plan Configuration

| Plan Code | Base Daily Rate (₹) | Coverage Multiplier |
|-----------|---------------------|---------------------|
| BASIC | 15.00 | 1.00 |
| PLUS | 20.00 | 1.25 |
| PREMIUM | 30.00 | 1.50 |

### Destination Multipliers

| Destination | Multiplier |
|-------------|------------|
| DOMESTIC | 1.00 |
| INTERNATIONAL | 1.80 |

### Age Multipliers

| Age Range | Multiplier |
|-----------|------------|
| 0-17 | 0.80 |
| 18-35 | 1.00 |
| 36-50 | 1.25 |
| 51-65 | 1.50 |
| 66+ | 2.00 |

### Calculation Example

For a 5-day trip to INTERNATIONAL with PLUS plan for a 28-year-old:
- Base Daily Rate: ₹20.00
- Travel Days: 5
- Age Multiplier (18-35): 1.00
- Destination Multiplier (INTERNATIONAL): 1.80
- Coverage Multiplier (PLUS): 1.25

**Premium** = 20 × 5 × 1.00 × 1.80 × 1.25 = **₹225.00**

---

## State Machine

### States

| State | Description | Terminal |
|-------|-------------|----------|
| RECEIVED | Request received, awaiting validation | No |
| VALIDATED | All validations passed, premium calculated | No |
| SUBMITTED | Submitted to insurer, awaiting processing | No |
| PENDING_CONFIRMATION | Insurer accepted, awaiting final callback | No |
| ISSUED | Policy successfully issued | **Yes** |
| FAILED | Rejected by insurer | **Yes** |
| NEEDS_RECONCILIATION | No response from insurer, manual intervention needed | No |

### Valid Transitions

```
RECEIVED → VALIDATED
VALIDATED → SUBMITTED
SUBMITTED → PENDING_CONFIRMATION
PENDING_CONFIRMATION → ISSUED
PENDING_CONFIRMATION → FAILED
PENDING_CONFIRMATION → NEEDS_RECONCILIATION
NEEDS_RECONCILIATION → ISSUED (via reconciliation)
NEEDS_RECONCILIATION → FAILED (via reconciliation)
FAILED → SUBMITTED (retry)
```

### Invalid Transitions (Rejected with 422)

- Any transition from `ISSUED` or `FAILED` (except FAILED → SUBMITTED retry)
- `PENDING_CONFIRMATION` → `VALIDATED` or `RECEIVED`
- `SUBMITTED` → `RECEIVED` or `VALIDATED`

---

## Reliability Features

### 1. Duplicate Intake Prevention

**Mechanism**: Database-level unique constraint on `(agent_id, client_request_id)` combined with `SELECT FOR UPDATE` row-level locking in a transaction.

**Behavior**:
- First request: Returns 201 Created with new resource
- Subsequent requests with same `agent_id` + `client_request_id`: Returns 200 OK with existing resource
- Concurrent requests: Handled by row lock, only one creates the record

**Test**: `DuplicateIntakeTest::test_concurrent_duplicate_requests_do_not_create_duplicates`

---

### 2. Duplicate Callback Idempotency

**Mechanism**: `provider_events` table with unique `provider_event_id` (format: `EVT-{provider_reference}-{event_type}`). Before processing, the system checks if the event was already processed.

**Behavior**:
- First callback: Processes normally, updates state
- Duplicate callbacks: Returns 200 with "Duplicate callback - already processed", no state change
- Cannot overwrite confirmed `ISSUED` state

**Test**: `DuplicateCallbackTest::test_duplicate_callback_is_idempotent`

---

## Reconciliation

### Stale Request Detection

Requests in `PENDING_CONFIRMATION` state are considered stale if:
- `updated_at` is older than `stale_minutes` threshold (default: 30 minutes)
- Has a `provider_reference` (was submitted to insurer)

### Reconciliation Actions

| Provider Status | Current State | Action |
|-----------------|---------------|--------|
| ISSUED | PENDING_CONFIRMATION | Transition to ISSUED |
| REJECTED | PENDING_CONFIRMATION | Transition to FAILED |
| TIMEOUT | PENDING_CONFIRMATION | Transition to NEEDS_RECONCILIATION |
| Any | ISSUED/FAILED | No action (none) |

---

## Database Schema

### policy_requests
```sql
- id (bigint, PK)
- client_request_id (varchar, 50)
- agent_id (varchar, 50)
- customer_name (varchar, 255)
- date_of_birth (date)
- destination (enum: DOMESTIC, INTERNATIONAL)
- trip_start (date)
- trip_end (date)
- plan_code (enum: BASIC, PLUS, PREMIUM)
- sum_insured (decimal 15,2)
- travel_days (int)
- age_at_travel (int)
- base_daily_rate (decimal 10,2)
- age_multiplier (decimal 4,2)
- destination_multiplier (decimal 4,2)
- coverage_multiplier (decimal 4,2)
- calculated_premium (decimal 15,2)
- status (enum: RECEIVED, VALIDATED, SUBMITTED, PENDING_CONFIRMATION, ISSUED, FAILED, NEEDS_RECONCILIATION)
- provider_reference (varchar, 100, nullable)
- policy_number (varchar, 50, nullable, unique)
- last_response (json, nullable)
- attempt_count (int, default: 0)
- created_at, updated_at

UNIQUE KEY unique_agent_client (agent_id, client_request_id)
INDEX idx_status (status)
INDEX idx_provider_reference (provider_reference)
```

### issuance_attempts
```sql
- id (bigint, PK)
- policy_request_id (bigint, FK)
- attempt_number (int)
- request_payload (json)
- response_payload (json, nullable)
- response_status (varchar, nullable)
- error_message (text, nullable)
- started_at (datetime)
- completed_at (datetime, nullable)
- created_at

INDEX idx_policy_request_id (policy_request_id)
```

### provider_events
```sql
- id (bigint, PK)
- policy_request_id (bigint, FK, nullable)
- provider_event_id (varchar, 100, unique)
- event_type (enum: ISSUED, REJECTED, TIMEOUT)
- payload (json)
- processed_at (datetime, nullable)
- created_at

UNIQUE KEY unique_provider_event (provider_event_id)
INDEX idx_policy_request_id (policy_request_id)
```

### policy_state_history
```sql
- id (bigint, PK)
- policy_request_id (bigint, FK)
- from_state (varchar, 50, nullable)
- to_state (varchar, 50)
- metadata (json, nullable)
- created_at

INDEX idx_policy_request_created (policy_request_id, created_at)
```

---

## Testing

### Run All Tests
```bash
php artisan test
```

### Test Categories

| Test File | Tests | Coverage |
|-----------|-------|----------|
| PremiumCalculatorTest | 11 | Unit tests for premium calculation logic |
| PolicyRequestTest | 13 | Intake, validation, listing, audit trail |
| IssuanceWorkflowTest | 8 | Submit, state transitions, scenarios |
| DuplicateIntakeTest | 5 | Duplicate prevention, concurrency |
| DuplicateCallbackTest | 5 | Callback idempotency |
| ReconciliationTest | 7 | Stale detection, bulk reconciliation |
| ExampleTest | 2 | Basic app health |

**Total**: 53 tests, 241 assertions

---

## Filament Admin Panel

### Access
1. Create admin user: `php artisan make:filament-user`
2. Visit: `http://localhost:8000/admin`

### Features

**Policy Request List**:
- Columns: ID, Client Request ID, Agent, Customer, Destination, Plan, Premium, Status, Created
- Filters: Status (multi-select), Agent, Destination, Plan, Date Range
- Actions: View, Retry Submission, Trigger Reconciliation

**Detail View**:
- Premium breakdown with all multipliers
- Full audit trail (state history)
- Issuance attempts with payloads
- Provider events with idempotency status

**Bulk Actions**:
- Trigger Reconciliation (for selected PENDING_CONFIRMATION/NEEDS_RECONCILIATION)

**Row Actions**:
- Retry Submission (for FAILED/NEEDS_RECONCILIATION)
- Trigger Reconciliation (for PENDING_CONFIRMATION/NEEDS_RECONCILIATION)

---

## Example cURL Commands

See `examples/curl-commands.sh` for a complete set of executable examples.

### Quick Examples

```bash
# Create request
curl -X POST http://localhost:8000/api/policy-requests \
  -H "Content-Type: application/json" \
  -d '{"client_request_id":"AGT-2026-0001","agent_id":"AGENT-17","customer_name":"John Doe","date_of_birth":"1990-01-15","destination":"INTERNATIONAL","trip_start":"2026-12-01","trip_end":"2026-12-10","plan_code":"PLUS","sum_insured":500000}'

# Submit with success scenario
curl -X POST http://localhost:8000/api/policy-requests/1/submit \
  -H "X-Mock-Scenario: success"

# Check status
curl -X GET http://localhost:8000/api/mock-insurer/status/PROV-20261023100000-ABC12345

# Reconcile
curl -X POST http://localhost:8000/api/admin/reconcile \
  -H "Content-Type: application/json" \
  -d '{"stale_minutes": 30}'
```

---

## Deployment Considerations

### Environment Variables
```env
APP_ENV=production
APP_DEBUG=false
DB_CONNECTION=mysql
DB_HOST=your-db-host
DB_DATABASE=policyflow
DB_USERNAME=your-user
DB_PASSWORD=your-password
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis
```

### Production Checklist
- [ ] Configure MySQL/PostgreSQL database
- [ ] Set up Redis for queues and cache
- [ ] Enable HTTPS
- [ ] Add authentication (Sanctum/Passport)
- [ ] Add rate limiting
- [ ] Configure queue workers for async processing
- [ ] Set up monitoring and logging
- [ ] Run `php artisan config:cache` and `php artisan route:cache`
- [ ] Run `php artisan migrate --force`

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | 2026-10-23 | Initial release with all core features |

---

## Support

For questions or issues, refer to the test files in `tests/Feature/` and `tests/Unit/` for usage examples.