# 🛡️ TripperShield (PolicyFlow)
### Enterprise-Grade Fault-Tolerant Travel Insurance Issuance & Reconciliation Engine

[![Laravel 11](https://img.shields.io/badge/Laravel-11.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![PHP 8.2+](https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![Filament v3](https://img.shields.io/badge/Filament-v3-F59E0B?style=for-the-badge&logo=filament&logoColor=white)](https://filamentphp.com)
[![Tests Passing](https://img.shields.io/badge/Tests-53%20Passed%20(241%20Assertions)-success?style=for-the-badge&logo=checkmarx&logoColor=white)]()

**TripperShield** is a robust, full-lifecycle travel insurance issuance engine designed to handle real-world insurer unreliability (timeouts, duplicate requests, network drops, and delayed callbacks). It features deterministic pricing, an immutable state machine, duplicate prevention, automated reconciliation, and **two modern user interfaces** so evaluators and interviewers can test the complete system visually without touching curl or terminal commands.

---

## 🌟 Key Interfaces at a Glance

| Interface | URL | Highlights |
|---|---|---|
| **🎨 Interactive Client Portal** | [`http://localhost:8000`](http://localhost:8000) | Dark-mode UI with **1-click Ready-Made Data button**, visual plan cards, **live premium calculator with formula breakdown**, and interactive insurer scenario simulator. |
| **📋 New Policy Request Form** | [`http://localhost:8000/policy/create`](http://localhost:8000/policy/create) | Interactive form with pre-filled sample data, plan cards (`BASIC`, `PLUS`, `PREMIUM`), and real-time multiplier calculations. |
| **📊 Policies Dashboard** | [`http://localhost:8000/policies`](http://localhost:8000/policies) | Filterable status table, live stats counter, quick-submit modal with scenario selection, and audit trail timeline viewer. |
| **⚙️ Filament Admin Panel** | [`http://localhost:8000/admin`](http://localhost:8000/admin) | Enterprise back-office dashboard with **"⚡ Fill Sample Data"** action, manual retries, bulk reconciliation, and relation managers. |
| **🔌 RESTful API** | [`http://localhost:8000/api`](http://localhost:8000/api) | High-performance JSON API with comprehensive validation, row-level concurrency locks, and idempotency keys. |

---

## ⚡ 60-Second Quick Start

```bash
# 1. Navigate to Backend directory
cd Backend

# 2. Start the development server (runs both frontend & admin)
php artisan serve --port=8000
```

> **The application is immediately accessible at:**
> - **Frontend Web App:** [http://localhost:8000](http://localhost:8000)
> - **Filament Operations Panel:** [http://localhost:8000/admin](http://localhost:8000/admin)
>   - **Default Admin:** `admin@tripper.com` / **Password:** `password`

---

## 🎯 What Interviewers / Evaluators Can Test in 2 Minutes

### Method 1: Visual Testing via Frontend Portal (No Terminal Needed!)
1. Open **[http://localhost:8000/policy/create](http://localhost:8000/policy/create)** in your browser.
2. Click **"⚡ Ready-Made Demo Data"** — watch all inputs instantly populate with valid mock traveler details.
3. Switch between **BASIC**, **PLUS**, and **PREMIUM** plans or change dates — watch the **Live Formula Breakdown** recalculate in real time.
4. Click **"Create Policy Request"** — automatically redirects to the Policy Detail page.
5. In the **Simulation Control Center**, pick an insurer scenario:
   - **`success`**: Watch the status change immediately to `ISSUED` with an immutable policy number.
   - **`failure`**: Simulates provider rejection → changes to `FAILED` and unlocks the **"Retry Submission"** button.
   - **`timeout`**: Simulates insurer dropping the connection → changes to `NEEDS_RECONCILIATION` and unlocks the **"Force Reconcile"** button.
   - **`duplicate`**: Simulates duplicate callback deliveries → demonstrates exact idempotency (no double-issuance).
6. Scroll down to inspect the **Audit Trail Timeline**, **Issuance Attempts**, and **Provider Callbacks**.

### Method 2: Testing via Filament Admin Panel
1. Open **[http://localhost:8000/admin/policy-requests](http://localhost:8000/admin/policy-requests)**.
2. Click **"New policy request"**.
3. Click the **"⚡ Fill Sample Data"** button at the top of the form modal.
4. Click **"Create"** — computed fields (`travel_days`, `calculated_premium`, `multipliers`) and audit records are generated automatically.
5. Filter, view details, inspect relation managers, or trigger bulk reconciliations.

---

## 🏛️ System Architecture

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

---

## 💰 Deterministic Pricing Model

$$\text{Premium} = \text{Base Daily Rate} \times \text{Travel Days} \times \text{Age Factor} \times \text{Destination Factor} \times \text{Coverage Factor}$$

- **Plans**: `BASIC` (₹15/day, 1.00×), `PLUS` (₹20/day, 1.25×), `PREMIUM` (₹30/day, 1.50×)
- **Destinations**: `DOMESTIC` (1.00×), `INTERNATIONAL` (1.80×)
- **Age Multipliers**: `0-17` (0.80×), `18-35` (1.00×), `36-50` (1.25×), `51-65` (1.50×), `66+` (2.00×)

---

## 🛡️ Fault-Tolerance & Reliability

1. **Duplicate Intake Prevention**: Composite unique constraint on `(agent_id, client_request_id)` with row-level transaction locks (`SELECT ... FOR UPDATE`). Repeated submissions return the existing policy with `200 OK`.
2. **Idempotent Callback Processing**: Unique `provider_event_id` tracking (`EVT-{provider_ref}-{type}`). Repeated callbacks are acknowledged safely without state corruption or duplicate policies.
3. **Strict State Transitions**: Enforces valid paths: `RECEIVED` ➔ `VALIDATED` ➔ `SUBMITTED` ➔ `PENDING_CONFIRMATION` ➔ `ISSUED` / `FAILED` / `NEEDS_RECONCILIATION`.
4. **Automated & Manual Reconciliation**: Resolves in-flight or timed-out requests by querying insurer status and reconciling state safely.

---

## 🧪 Comprehensive Automated Test Suite

```bash
cd Backend
php artisan test
```

```
Tests:    53 passed (241 assertions)
Duration: 1.10s
```

- ✅ `PremiumCalculatorTest` (11 unit tests)
- ✅ `PolicyRequestTest` (13 feature tests)
- ✅ `IssuanceWorkflowTest` (8 feature tests)
- ✅ `DuplicateIntakeTest` (5 feature tests)
- ✅ `DuplicateCallbackTest` (5 feature tests)
- ✅ `ReconciliationTest` (7 feature tests)

---

## 📖 Deep-Dive Documentation

- 📘 **[End-to-End Workflow Guide](file:///Users/gouravdash/Downloads/Tripper/Backend/END_TO_END_WORKFLOW.md)** — Step-by-step walkthrough of all phases, visual UI flows, state machine rules, and curl recipes.
- 📙 **[Backend Technical Guide](file:///Users/gouravdash/Downloads/Tripper/Backend/README.md)** — Architecture, database schemas, configuration, and production deployment checklists.
- 📗 **[API Documentation](file:///Users/gouravdash/Downloads/Tripper/Backend/API_DOCUMENTATION.md)** — Full REST API specifications with JSON request/response contracts.
- 📕 **[AI Usage Disclosure](file:///Users/gouravdash/Downloads/Tripper/Backend/AI_USAGE.md)** — Details of tools, architecture design, and development collaboration.
