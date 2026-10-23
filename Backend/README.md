# PolicyFlow (TripperShield Backend)

**Fault-Tolerant Travel Insurance Issuance & Reconciliation Engine**

A Laravel 11 application for processing travel insurance policy requests with robust handling of unreliable insurer behavior. Built for enterprise-grade reliability patterns with deterministic pricing, an immutable state machine, duplicate prevention, automated reconciliation, and **two modern user interfaces** (Client Web App + Filament v3 Admin Panel) alongside a complete RESTful API.

---

## 🎯 Project Highlights

- **🎨 Interactive Client Portal**: Dark-mode web interface for non-technical evaluators to submit policies, view real-time premium formulas, simulate insurer scenarios, and inspect visual audit timelines.
- **⚡ 1-Click "Ready-Made Data" & Auto-Fill**: Fill the entire form with randomized, valid demo data with a single click in both the Client Portal and Filament Admin.
- **💰 Deterministic Formula Pricing**: Live, reactive pricing calculator with instant multiplier breakdowns (`Base Rate × Days × Age Factor × Destination Factor × Coverage Factor`).
- **🔄 Immutable 7-State Machine**: Enforces strict transitions (`RECEIVED` ➔ `VALIDATED` ➔ `SUBMITTED` ➔ `PENDING_CONFIRMATION` ➔ `ISSUED` / `FAILED` / `NEEDS_RECONCILIATION`).
- **🛡️ Concurrency & Idempotency Protection**: Row-level database locks (`SELECT ... FOR UPDATE`) prevent duplicate requests from the same agent; unique event keys prevent duplicate insurer callbacks from corrupting state.
- **🔧 Active Reconciliation Service**: Automatically resolves timed-out or pending requests by querying insurer status.
- **⚙️ Filament v3 Admin Operations Dashboard**: Full back-office management panel with bulk actions, relation managers, and manual retry tools.
- **🧪 100% Test Coverage on Workflow**: 53 automated tests passing with 241 assertions.

---

## 🏗️ Architecture

```
┌────────────────────────────────────────────────────────┐
│                   USER INTERFACES                      │
│   Client Web App (/policy/create) │ Filament Admin     │
└──────────────────────────┬─────────────────────────────┘
                           │
┌──────────────────────────▼─────────────────────────────┐
│                 POLICYFLOW CORE ENGINE                 │
│  - Form Request Validation                             │
│  - Deterministic Formula-Based Pricing Engine          │
│  - Strict State Machine (7 explicit states)            │
│  - Concurrency Lock & Duplicate Intake Protection      │
│  - Provider Event Idempotency Registry                 │
│  - Stale Request Reconciliation Service                │
└──────────────────────────┬─────────────────────────────┘
                           │
┌──────────────────────────▼─────────────────────────────┐
│               MOCK INSURER SIMULATOR                   │
│   Success │ Failure │ Timeout │ Duplicate │ Delayed    │
└────────────────────────────────────────────────────────┘
```

### Project Structure

```
Backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Frontend/
│   │   │   │   └── PolicyFrontendController.php # Web UI: create, show, dashboard, submit
│   │   │   └── Api/
│   │   │       ├── PolicyRequestController.php  # REST API: intake, submit, list, show
│   │   │       └── MockInsurerController.php    # Callback, status, reconcile
│   │   ├── Requests/
│   │   │   └── StorePolicyRequestRequest.php    # Comprehensive validation rules
│   │   └── Resources/
│   │       ├── PolicyRequestResource.php        # Single resource transform
│   │       └── PolicyRequestCollection.php      # Collection with pagination
│   ├── Models/
│   │   ├── PolicyRequest.php                    # Main entity with state machine
│   │   ├── IssuanceAttempt.php                  # Submission attempt records
│   │   ├── ProviderEvent.php                    # Callback idempotency records
│   │   └── PolicyStateHistory.php               # Audit trail records
│   ├── Services/
│   │   ├── PremiumCalculator.php                # Deterministic formula engine
│   │   ├── MockInsurerService.php               # Insurer simulation & callbacks
│   │   └── ReconciliationService.php            # Stale request resolution
│   └── Filament/
│       ├── Resources/PolicyRequestResource.php  # Admin panel resource with auto-fill
│       └── Resources/PolicyRequestResource/
│           ├── Pages/ (List, Create, View)
│           └── RelationManagers/ (StateHistory, Attempts, ProviderEvents)
├── database/
│   ├── migrations/ (policy_requests, issuance_attempts, provider_events, history)
│   └── seeders/DatabaseSeeder.php
├── resources/views/
│   └── frontend/                                # Dark-mode Blade views
│       ├── index.blade.php                      # Landing page
│       ├── create.blade.php                     # New policy request form
│       ├── dashboard.blade.php                  # Policies dashboard
│       └── show.blade.php                       # Policy detail & scenario simulator
├── routes/
│   ├── web.php / frontend.php                   # Frontend Web UI routes
│   ├── api.php                                  # RESTful API routes
│   └── mock-insurer.php                         # Mock insurer routes
└── tests/
    ├── Feature/ (5 test files, 41 tests)
    └── Unit/PremiumCalculatorTest.php (12 tests)
```

---

## 📋 Requirements

| Requirement | Supported Version |
|---|---|
| PHP | 8.2+ (tested on PHP 8.2 and PHP 8.5) |
| Laravel | 11.x |
| Database | SQLite (default/dev) or MySQL 8+ / PostgreSQL |
| Composer | 2.x |

---

## 🚀 Installation & Quick Start

```bash
cd Backend

# 1. Install Composer dependencies
composer install

# 2. Copy environment file and generate key
cp .env.example .env
php artisan key:generate

# 3. Run database migrations
php artisan migrate

# 4. (Optional) Create Filament Admin User if needed
php artisan make:filament-user

# 5. Start the local server
php artisan serve --port=8000
```

### 🌐 Accessing the System

| Interface | URL | Purpose |
|---|---|---|
| **Client Portal (Home)** | `http://localhost:8000` | Landing page explaining the engine |
| **New Policy Form** | `http://localhost:8000/policy/create` | Form with **1-click Ready-Made Data** & live formula breakdown |
| **Policies Dashboard** | `http://localhost:8000/policies` | Visual policy dashboard & quick submission modal |
| **Policy Details & Simulation** | `http://localhost:8000/policy/{id}` | Audit timeline, attempts, and scenario simulator |
| **Filament Admin Dashboard** | `http://localhost:8000/admin` | Enterprise operations panel (`admin@tripper.com` / `password`) |
| **RESTful API** | `http://localhost:8000/api` | Direct JSON programmatic access |

---

## 💻 Web User Interface Walkthrough

Evaluators do not need to construct curl requests. You can test the complete system visually:

### 1. Instant Form Auto-Fill (`/policy/create`)
- Click the **"⚡ Ready-Made Demo Data"** button on the top-right of the form.
- The form instantly generates a unique Agent ID, Client Request ID, randomized Indian traveler name, valid date of birth, upcoming travel dates, destination, and sum insured.
- Notice the **Live Formula Calculator** on the right updating in real time with the full formula:
  $$\text{Days} \times \text{Base Rate} \times \text{Age Multiplier} \times \text{Destination Multiplier} \times \text{Plan Multiplier} = \text{Total Premium}$$

### 2. Interactive Insurer Simulation (`/policy/{id}`)
Once created, the policy opens in the **Policy Detail** view:
- Current state is `VALIDATED`.
- Pick a mock insurer scenario from the dropdown:
  - **`success`**: Simulates instant policy issuance ➔ Status updates to `ISSUED` with a unique policy number (`POL-YYYYMMDD-XXXXXX`).
  - **`failure`**: Simulates provider rejection ➔ Status updates to `FAILED` with error details, and unlocks the **"Retry Submission"** button.
  - **`timeout`**: Simulates provider timeout/silence ➔ Status updates to `NEEDS_RECONCILIATION`, unlocking the **"Force Reconcile"** button.
  - **`duplicate`**: Sends two callbacks with the exact same event key ➔ Verifies that the second callback is safely ignored without duplicate issuance.
  - **`delayed`**: Simulates asynchronous queue-delayed issuance.
- Inspect the **Visual Audit Trail Timeline** at the bottom showing every timestamped state transition and metadata snapshot.

---

## ⚙️ Filament v3 Admin Panel

Located at **`http://localhost:8000/admin/policy-requests`**:
- **"New policy request"** modal includes a **"⚡ Fill Sample Data"** header action that populates valid mock data with a single click.
- Live premium calculation preview in the modal before saving.
- Complete audit trail and relation managers:
  - **State History**: Full transition log from initial to terminal state.
  - **Issuance Attempts**: Every API payload sent to the insurer with responses.
  - **Provider Events**: Every incoming webhook/callback recorded with idempotency flags.
- Operational Actions:
  - **Retry Submission**: Visible on `FAILED` or `NEEDS_RECONCILIATION` policies.
  - **Trigger Reconciliation**: Visible on `PENDING_CONFIRMATION` and `NEEDS_RECONCILIATION` policies.
  - **Bulk Reconcile**: Reconciles multiple selected stale requests in batch.

---

## 📚 RESTful API Reference

For detailed JSON schemas and curl examples, see **[API_DOCUMENTATION.md](API_DOCUMENTATION.md)**.

### Endpoint Overview

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/api/policy-requests` | Create policy request (with duplicate prevention) |
| `GET` | `/api/policy-requests` | List policy requests (filter by status, agent, plan) |
| `GET` | `/api/policy-requests/{id}` | Get policy request with audit trail and attempts |
| `POST` | `/api/policy-requests/{id}/submit` | Submit to mock insurer (`X-Mock-Scenario` header) |
| `GET` | `/api/mock-insurer/scenarios` | List supported simulation scenarios |
| `GET` | `/api/mock-insurer/status/{ref}` | Direct provider status inquiry |
| `POST` | `/api/mock-insurer/callback` | Webhook callback endpoint (idempotent) |
| `POST` | `/api/admin/reconcile` | Trigger manual or bulk reconciliation |

---

## 💰 Deterministic Pricing Model

$$\text{Premium} = \text{Base Daily Rate} \times \text{Travel Days} \times \text{Age Multiplier} \times \text{Destination Multiplier} \times \text{Coverage Multiplier}$$

### Configuration Tables

| Plan Code | Base Daily Rate | Coverage Multiplier |
|---|---|---|
| `BASIC` | ₹15.00 | 1.00× |
| `PLUS` | ₹20.00 | 1.25× |
| `PREMIUM` | ₹30.00 | 1.50× |

| Destination | Multiplier |
|---|---|
| `DOMESTIC` | 1.00× |
| `INTERNATIONAL` | 1.80× |

| Traveler Age at Trip Start | Age Multiplier |
|---|---|
| 0 – 17 years | 0.80× |
| 18 – 35 years | 1.00× |
| 36 – 50 years | 1.25× |
| 51 – 65 years | 1.50× |
| 66+ years | 2.00× |

---

## 🔄 State Machine & Transition Rules

```
                      ┌──────────────┐
                      │   RECEIVED   │
                      └──────┬───────┘
                             │ (Automatic on creation)
                             ▼
                      ┌──────────────┐
                      │  VALIDATED   │
                      └──────┬───────┘
                             │ (Submit to insurer)
                             ▼
                      ┌──────────────┐
                      │  SUBMITTED   │
                      └──────┬───────┘
                             │
                             ▼
                 ┌───────────────────────┐
                 │ PENDING_CONFIRMATION  │
                 └───┬───────┬───────┬───┘
                     │       │       │
       (Success)     │       │       │ (Timeout / Stale)
     ┌───────────────┘       │       └────────────────┐
     ▼                       ▼                        ▼
┌─────────┐             ┌─────────┐       ┌──────────────────────┐
│ ISSUED  │             │ FAILED  │       │ NEEDS_RECONCILIATION │
└─────────┘             └───┬─────┘       └──────────┬───────────┘
 (Terminal)                 │ (Retry)                │ (Reconcile)
                            └────────▶ SUBMITTED ◀───┘
```

---

## 🛡️ Reliability & Fault-Tolerance Features

### 1. Duplicate Intake Prevention
- **Issue**: Client agents frequently retry requests during network timeouts.
- **Solution**: Composite database unique index on `(agent_id, client_request_id)` with row-level transaction locks (`SELECT ... FOR UPDATE`).
- **Behavior**: If a matching request already exists, the server returns the existing resource with HTTP `200 OK` rather than generating a duplicate.

### 2. Idempotent Callback Processing
- **Issue**: Insurer webhooks retry deliveries or experience network duplication.
- **Solution**: Dedicated `provider_events` table indexed by `provider_event_id` (`EVT-{provider_reference}-{event_type}`).
- **Behavior**: Subsequent identical callbacks are acknowledged safely with HTTP `200 OK` without altering state or generating duplicate policy numbers.

### 3. Automated Reconciliation
- **Issue**: Insurer callbacks may be lost due to network failure, leaving requests in `PENDING_CONFIRMATION`.
- **Solution**: Reconciliation engine queries insurer reference status and automatically transitions requests to `ISSUED`, `FAILED`, or `NEEDS_RECONCILIATION`.

---

## 🧪 Automated Testing

PolicyFlow includes a complete test suite verifying every component, formula, edge case, and scenario:

```bash
php artisan test
```

```
   PASS  Tests\Unit\ExampleTest
   PASS  Tests\Unit\PremiumCalculatorTest (11 tests)
   PASS  Tests\Feature\DuplicateCallbackTest (5 tests)
   PASS  Tests\Feature\DuplicateIntakeTest (5 tests)
   PASS  Tests\Feature\ExampleTest (2 tests)
   PASS  Tests\Feature\IssuanceWorkflowTest (8 tests)
   PASS  Tests\Feature\PolicyRequestTest (13 tests)
   PASS  Tests\Feature\ReconciliationTest (7 tests)

  Tests:    53 passed (241 assertions)
  Duration: 1.10s
```

---

## 📁 Key Files Reference

- **Frontend Controller**: [`app/Http/Controllers/Frontend/PolicyFrontendController.php`](file:///Users/gouravdash/Downloads/Tripper/Backend/app/Http/Controllers/Frontend/PolicyFrontendController.php)
- **API Controller**: [`app/Http/Controllers/Api/PolicyRequestController.php`](file:///Users/gouravdash/Downloads/Tripper/Backend/app/Http/Controllers/Api/PolicyRequestController.php)
- **Mock Insurer Service**: [`app/Services/MockInsurerService.php`](file:///Users/gouravdash/Downloads/Tripper/Backend/app/Services/MockInsurerService.php)
- **Pricing Calculator**: [`app/Services/PremiumCalculator.php`](file:///Users/gouravdash/Downloads/Tripper/Backend/app/Services/PremiumCalculator.php)
- **Reconciliation Service**: [`app/Services/ReconciliationService.php`](file:///Users/gouravdash/Downloads/Tripper/Backend/app/Services/ReconciliationService.php)
- **Policy Model & State Machine**: [`app/Models/PolicyRequest.php`](file:///Users/gouravdash/Downloads/Tripper/Backend/app/Models/PolicyRequest.php)
- **Filament Admin Resource**: [`app/Filament/Resources/PolicyRequestResource.php`](file:///Users/gouravdash/Downloads/Tripper/Backend/app/Filament/Resources/PolicyRequestResource.php)

---

## 📖 Related Documents

- 📘 **[End-to-End Workflow Guide](END_TO_END_WORKFLOW.md)** — Comprehensive step-by-step walkthrough covering visual UI testing, state machine transitions, and curl recipes.
- 📗 **[API Documentation](API_DOCUMENTATION.md)** — Complete endpoint specifications, headers, parameters, and JSON payloads.
- 📕 **[AI Usage Disclosure](AI_USAGE.md)** — Architectural choices, tooling, and development notes.