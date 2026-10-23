# PolicyFlow

**Fault-Tolerant Insurance Issuance & Reconciliation Engine**

A Laravel 11 application for processing travel insurance policy requests with robust handling of unreliable insurer behavior. Built for campus placement assessment - demonstrates enterprise-grade reliability patterns without real payment/insurer integrations.

---

## 🎯 Project Overview

PolicyFlow implements a complete insurance policy issuance workflow with focus on fault tolerance:

- **Policy Intake**: RESTful API with comprehensive validation
- **Premium Calculation**: Deterministic formula-based pricing
- **State Machine**: Explicit transitions with audit trail
- **Mock Insurer**: Configurable scenarios (success, failure, timeout, duplicate)
- **Reliability**: Duplicate prevention, idempotent callbacks
- **Reconciliation**: Automated stale request detection and resolution
- **Admin Panel**: Filament v3 operations dashboard

---

## 🏗️ Architecture

```
┌─────────────┐     ┌──────────────────┐     ┌─────────────────┐
│   Agents    │────▶│  PolicyFlow API  │────▶│  Mock Insurer   │
│  (Clients)  │     │   (Laravel 11)   │     │  (Internal)     │
└─────────────┘     └──────────────────┘     └─────────────────┘
                           │
                    ┌──────┴──────┐
                    ▼             ▼
              ┌──────────┐  ┌────────────┐
              │ Database │  │  Queue     │
              │ (MySQL)  │  │  (Redis)   │
              └──────────┘  └────────────┘
```

### Project Structure

```
Backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/
│   │   │   ├── PolicyRequestController.php    # Intake, submit, list, show
│   │   │   └── MockInsurerController.php      # Callback, status, reconcile
│   │   ├── Requests/
│   │   │   └── StorePolicyRequestRequest.php  # Validation rules
│   │   └── Resources/
│   │       ├── PolicyRequestResource.php      # Single resource transform
│   │       └── PolicyRequestCollection.php    # Collection with pagination
│   ├── Models/
│   │   ├── PolicyRequest.php                  # Main entity with state machine
│   │   ├── IssuanceAttempt.php                # Submission attempt records
│   │   ├── ProviderEvent.php                  # Callback idempotency records
│   │   └── PolicyStateHistory.php             # Audit trail
│   ├── Services/
│   │   ├── PremiumCalculator.php              # Pricing logic
│   │   ├── MockInsurerService.php             # Insurer simulation
│   │   └── ReconciliationService.php          # Stale request resolution
│   └── Filament/
│       └── Resources/PolicyRequestResource.php # Admin panel
├── database/
│   ├── migrations/
│   │   ├── create_policy_requests_table.php
│   │   ├── create_issuance_attempts_table.php
│   │   ├── create_provider_events_table.php
│   │   └── create_policy_state_history_table.php
│   └── seeders/DatabaseSeeder.php
├── routes/
│   ├── api.php
│   └── mock-insurer.php
├── tests/
│   ├── Feature/ (5 test files, 39 tests)
│   └── Unit/PremiumCalculatorTest.php (11 tests)
├── examples/
│   ├── valid-request.json
│   ├── invalid-dates.json
│   └── curl-commands.sh
├── .env.example
├── README.md
├── API_DOCUMENTATION.md
└── AI_USAGE.md
```

---

## 📋 Requirements

| Requirement | Version |
|-------------|---------|
| PHP | 8.2+ |
| Laravel | 11+ |
| Database | SQLite (dev) / MySQL 8+ (prod) |
| Composer | 2.x |
| Node.js | 18+ (for Filament assets) |

---

## 🚀 Installation

### Quick Start

```bash
cd Backend

# Install PHP dependencies
composer install

# Install Node dependencies (for Filament)
npm install && npm run build

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate

# (Optional) Seed database
php artisan db:seed

# Create Filament admin user
php artisan make:filament-user

# Start development server
php artisan serve
```

The application will be available at:
- **API**: `http://localhost:8000/api`
- **Admin Panel**: `http://localhost:8000/admin`

### Environment Configuration

```env
# Development (SQLite)
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite

# Production (MySQL)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=policyflow
DB_USERNAME=root
DB_PASSWORD=

# Queue & Cache (Production)
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis
```

---

## 📚 API Documentation

For complete API documentation with request/response examples, see **[API_DOCUMENTATION.md](API_DOCUMENTATION.md)**.

### Quick Reference

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/policy-requests` | Create policy request |
| GET | `/api/policy-requests` | List with filters |
| GET | `/api/policy-requests/{id}` | Get with audit trail |
| POST | `/api/policy-requests/{id}/submit` | Submit to insurer |
| GET | `/api/mock-insurer/scenarios` | List mock scenarios |
| GET | `/api/mock-insurer/status/{ref}` | Provider status lookup |
| POST | `/api/mock-insurer/callback` | Insurer callback |
| POST | `/api/admin/reconcile` | Trigger reconciliation |

---

## 💰 Premium Calculation

### Formula
```
premium = base_daily_rate × travel_days × age_multiplier × destination_multiplier × coverage_multiplier
```

### Pricing Tables

#### Plans
| Plan | Base Daily Rate | Coverage Multiplier |
|------|-----------------|---------------------|
| BASIC | ₹15.00 | 1.00 |
| PLUS | ₹20.00 | 1.25 |
| PREMIUM | ₹30.00 | 1.50 |

#### Destinations
| Destination | Multiplier |
|-------------|------------|
| DOMESTIC | 1.00 |
| INTERNATIONAL | 1.80 |

#### Age Ranges
| Age | Multiplier |
|-----|------------|
| 0-17 | 0.80 |
| 18-35 | 1.00 |
| 36-50 | 1.25 |
| 51-65 | 1.50 |
| 66+ | 2.00 |

### Example Calculation
**5 days, Age 28, INTERNATIONAL, PLUS**
```
20 × 5 × 1.00 × 1.80 × 1.25 = ₹225.00
```

---

## 🔄 State Machine

```
RECEIVED → VALIDATED → SUBMITTED → PENDING_CONFIRMATION → ISSUED
                                    ↘                     ↗
                                     → FAILED (terminal)
                                     → NEEDS_RECONCILIATION
```

### State Descriptions

| State | Description | Terminal |
|-------|-------------|----------|
| RECEIVED | Request received, awaiting validation | No |
| VALIDATED | Validations passed, premium calculated | No |
| SUBMITTED | Sent to insurer, awaiting processing | No |
| PENDING_CONFIRMATION | Insurer accepted, awaiting callback | No |
| ISSUED | Policy successfully issued | **Yes** |
| FAILED | Rejected by insurer | **Yes** |
| NEEDS_RECONCILIATION | No response, manual intervention needed | No |

### Transition Rules
- **Auto**: RECEIVED → VALIDATED (immediate after creation)
- **Manual**: VALIDATED → SUBMITTED (via `/submit` endpoint)
- **Auto**: SUBMITTED → PENDING_CONFIRMATION (for immediate scenarios)
- **Callback**: PENDING_CONFIRMATION → ISSUED/FAILED/NEEDS_RECONCILIATION
- **Retry**: FAILED → SUBMITTED (re-submit with new scenario)
- **Reconcile**: NEEDS_RECONCILIATION → ISSUED/FAILED (via admin)

---

## 🎭 Mock Insurer Scenarios

Control behavior via `X-Mock-Scenario` header on submit:

| Scenario | Header Value | Behavior |
|----------|--------------|----------|
| Success | `success` | Immediate ISSUED callback |
| Delayed | `delayed` | 2-second delay then ISSUED |
| Failure | `failure` | Immediate REJECTED callback |
| Duplicate | `duplicate` | Sends ISSUED callback twice |
| Timeout | `timeout` | No callback (requires reconciliation) |

---

## 🛡️ Reliability Features

### 1. Duplicate Intake Prevention
**Problem**: Agents may retry requests due to network issues.

**Solution**: 
- Database UNIQUE constraint on `(agent_id, client_request_id)`
- `SELECT FOR UPDATE` row-level lock in transaction
- Returns 200 with existing resource on duplicate

**Test**: `DuplicateIntakeTest::test_concurrent_duplicate_requests_do_not_create_duplicates`

### 2. Duplicate Callback Idempotency
**Problem**: Insurer may send duplicate callbacks (network retry, bug).

**Solution**:
- `provider_events` table with unique `provider_event_id`
- Format: `EVT-{provider_reference}-{event_type}`
- Atomic check-then-process in transaction
- Duplicate callbacks return 200, no state change

**Test**: `DuplicateCallbackTest::test_duplicate_callback_is_idempotent`

---

## 🔧 Reconciliation

### Stale Detection
Requests in `PENDING_CONFIRMATION` with `updated_at` older than threshold (default 30 min) are stale.

### Reconciliation Actions

| Provider Status | Action |
|-----------------|--------|
| ISSUED | Transition to ISSUED, generate policy number |
| REJECTED | Transition to FAILED |
| TIMEOUT | Transition to NEEDS_RECONCILIATION |
| Already terminal | No action |

### Triggers
- **Manual**: `POST /api/admin/reconcile` with `provider_reference`
- **Bulk**: `POST /api/admin/reconcile` with `stale_minutes`
- **Scheduled**: Run via Laravel scheduler (add to `Kernel.php`)

---

## 🗄️ Database Schema

### Core Tables

**policy_requests** - Main entity with all request data, computed premium, and state
```sql
- id, client_request_id, agent_id, customer_name, date_of_birth
- destination, trip_start, trip_end, plan_code, sum_insured
- travel_days, age_at_travel, base_daily_rate, age_multiplier
- destination_multiplier, coverage_multiplier, calculated_premium
- status (7 states), provider_reference, policy_number
- last_response (JSON), attempt_count, timestamps
- UNIQUE(agent_id, client_request_id)
```

**issuance_attempts** - Each submission attempt
```sql
- id, policy_request_id, attempt_number
- request_payload (JSON), response_payload (JSON)
- response_status, error_message, started_at, completed_at
```

**provider_events** - Idempotent callback processing
```sql
- id, policy_request_id, provider_event_id (UNIQUE)
- event_type, payload (JSON), processed_at
```

**policy_state_history** - Full audit trail
```sql
- id, policy_request_id, from_state, to_state
- metadata (JSON), created_at
```

---

## 🧪 Testing

### Run All Tests
```bash
php artisan test
```

### Test Suite Summary

| Test File | Tests | Coverage |
|-----------|-------|----------|
| PremiumCalculatorTest | 11 | All plan/destination/age combos, edge cases |
| PolicyRequestTest | 13 | Intake, validation, listing, audit trail |
| IssuanceWorkflowTest | 8 | Submit, transitions, all 5 scenarios |
| DuplicateIntakeTest | 5 | Prevention, concurrency, uniqueness |
| DuplicateCallbackTest | 5 | Idempotency, event recording, overwrite prevention |
| ReconciliationTest | 7 | Stale detection, bulk, retry, unknown ref |
| ExampleTest | 2 | Basic health checks |

**Total: 53 tests, 241 assertions** ✅

### Run Specific Tests
```bash
# Unit tests
php artisan test tests/Unit/PremiumCalculatorTest.php

# Feature tests by category
php artisan test tests/Feature/PolicyRequestTest.php
php artisan test tests/Feature/IssuanceWorkflowTest.php
php artisan test tests/Feature/DuplicateIntakeTest.php
php artisan test tests/Feature/DuplicateCallbackTest.php
php artisan test tests/Feature/ReconciliationTest.php
```

---

## 🖥️ Admin Panel (Filament v3)

### Access
1. Create user: `php artisan make:filament-user`
2. Login at: `http://localhost:8000/admin`

### Features

**Policy Request List**
- Columns: ID, Client Request ID, Agent, Customer, Destination, Plan, Premium, Status, Created
- Filters: Status (multi), Agent, Destination, Plan, Date Range
- Sortable: All columns

**Detail View**
- **Overview**: Customer, trip, plan, premium breakdown
- **Audit Trail**: Complete state history with metadata
- **Issuance Attempts**: All submissions with request/response payloads
- **Provider Events**: Callbacks with idempotency status

**Actions**
| Action | Available For |
|--------|---------------|
| View | All states |
| Retry Submission | FAILED, NEEDS_RECONCILIATION |
| Trigger Reconciliation | PENDING_CONFIRMATION, NEEDS_RECONCILIATION |

**Bulk Actions**
- Trigger Reconciliation (selected stale requests)

---

## 📁 Example Files

| File | Purpose |
|------|---------|
| `examples/valid-request.json` | Valid request payload |
| `examples/invalid-dates.json` | Invalid dates example |
| `examples/curl-commands.sh` | 14 executable cURL examples |

### Run Examples
```bash
chmod +x examples/curl-commands.sh
./examples/curl-commands.sh
```

---

## 🔍 Code Quality

```bash
# Format code (Laravel Pint)
php artisan pint

# Static analysis (PHPStan)
./vendor/bin/phpstan analyse

# Run tests with coverage
php artisan test --coverage
```

---

## 🚢 Production Deployment

### Checklist
- [ ] Configure MySQL/PostgreSQL in `.env`
- [ ] Set up Redis for queues/cache/sessions
- [ ] Enable HTTPS (SSL certificate)
- [ ] Add authentication (Laravel Sanctum recommended)
- [ ] Configure rate limiting (`RateLimiter::for`)
- [ ] Set up queue workers: `php artisan queue:work --daemon`
- [ ] Add scheduler for reconciliation: `* * * * * php artisan schedule:run`
- [ ] Run optimizations:
  ```bash
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
  php artisan event:cache
  ```
- [ ] Set `APP_DEBUG=false`, `APP_ENV=production`

### Queue Worker (for delayed scenario)
```bash
# Install supervisor or use systemd
php artisan queue:work redis --sleep=3 --tries=3 --timeout=60
```

---

## 📝 License

This is a demo project for assessment purposes. Not for production use without proper security hardening.

---

## 🤝 Contributing

1. Fork the repository
2. Create feature branch
3. Add tests for new functionality
4. Ensure all tests pass: `php artisan test`
5. Submit PR

---

## 📖 Related Documentation

- [API Documentation](API_DOCUMENTATION.md) - Complete endpoint reference
- [AI Usage Disclosure](AI_USAGE.md) - Development assistance details
- [Laravel 11 Documentation](https://laravel.com/docs/11.x)
- [Filament v3 Documentation](https://filamentphp.com/docs/3.x)

---

## 🏷️ Version

**1.0.0** - Initial release with all mandatory scope + Filament admin stretch feature

**Candidate ID**: 12307549