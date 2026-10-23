# PolicyFlow - End-to-End Workflow Guide

**Complete Lifecycle, Fault-Tolerance Engine, and Testing Walkthrough**

This guide documents the full lifecycle of a travel insurance policy request in PolicyFlow, detailing how requests move through intake, validation, pricing, insurer submission, callback processing, failure retry, and stale reconciliation across **all three interfaces**:
1. **Interactive Client Web Portal** (Evaluator-friendly visual UI)
2. **Filament v3 Back-Office Operations Panel**
3. **Enterprise RESTful API**

---

## 🎬 Workflow Architecture

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   CLIENT / UI   │───▶│     INTAKE      │───▶│   SUBMISSION    │───▶│   MOCK INSURER  │
│  Auto-Fill Form │    │ & Deterministic │    │    TO INSURER   │    │    SIMULATOR    │
│  or API Request │    │   Calculation   │    │  (Scenario Pick)│    │(5 Scenarios)    │
└─────────────────┘    └─────────────────┘    └─────────────────┘    └────────┬────────┘
                                                                              │
                       ┌─────────────────┐    ┌─────────────────┐             │
                       │ RECONCILIATION  │◀───│   IDEMPOTENT    │◀────────────┘
                       │   (if stale)    │    │    CALLBACK     │
                       └─────────────────┘    └─────────────────┘
```

---

## 🧭 The 3 Ways to Experience the Workflow

| Method | Best For | Entry Point |
|---|---|---|
| **🎨 Interactive Client Portal** | **Fastest, zero-setup testing for evaluators & interviewers** | [http://localhost:8000/policy/create](http://localhost:8000/policy/create) |
| **⚙️ Filament Admin Dashboard** | Back-office operational auditing, bulk reconciliations | [http://localhost:8000/admin/policy-requests](http://localhost:8000/admin/policy-requests) |
| **🔌 Direct RESTful API** | Automated programmatic integrations & cURL script testing | [http://localhost:8000/api](http://localhost:8000/api) |

---

## ⚡ 5-Minute Evaluator Test Script (Visual UI)

You can verify the entire system in under 5 minutes without writing a single terminal command:

### Step 1: Create a Policy with 1-Click Demo Data
1. Open **[http://localhost:8000/policy/create](http://localhost:8000/policy/create)**.
2. Click the green **"⚡ Ready-Made Demo Data"** button on the top-right.
   - All inputs (Agent ID, unique Client Request ID, traveler name, DOB, upcoming travel dates, destination, plan code, and sum insured) are instantly populated.
3. Switch between **BASIC**, **PLUS**, and **PREMIUM** plan cards or change travel dates.
   - Watch the **Live Formula Breakdown** on the right recalculate in real-time.
4. Click **"Create Policy Request"**. You are automatically redirected to the **Policy Detail & Simulation** page.

### Step 2: Test Scenario 1 — Successful Issuance (`success`)
1. On the Policy Detail page, the policy starts in the `VALIDATED` state.
2. In the **Simulation Control Center**, ensure scenario is set to **`success`**.
3. Click **"Submit to Mock Insurer"**.
4. The page refreshes with:
   - Status updated to **`ISSUED`** (Green badge).
   - Generated **Policy Number**: `POL-YYYYMMDD-XXXXXX`.
   - Generated **Provider Reference**: `PROV-...`.
   - Scroll down to the **Audit Trail Timeline** to see the exact progression: `null ➔ RECEIVED ➔ VALIDATED ➔ SUBMITTED ➔ PENDING_CONFIRMATION ➔ ISSUED`.

### Step 3: Test Scenario 2 — Insurer Rejection & Manual Retry (`failure`)
1. Return to **[http://localhost:8000/policy/create](http://localhost:8000/policy/create)** and click **"⚡ Ready-Made Demo Data"** ➔ **Create**.
2. On the detail page, select **`failure`** in the scenario dropdown.
3. Click **"Submit to Mock Insurer"**.
4. Notice:
   - Status becomes **`FAILED`** (Red badge).
   - An issuance attempt record captures the mock provider's rejection response.
   - The **"Retry Submission"** button unlocks.
5. Click **"Retry Submission"** and choose `success` ➔ The policy transitions back to `SUBMITTED` and issues successfully!

### Step 4: Test Scenario 3 — Timeout & Active Reconciliation (`timeout`)
1. Create another request via **"⚡ Ready-Made Demo Data"**.
2. Select **`timeout`** in the scenario dropdown and click **"Submit"**.
3. Notice:
   - Status transitions to **`NEEDS_RECONCILIATION`** (Amber badge) because the insurer dropped the callback.
   - The **"Force Reconcile"** button appears.
4. Click **"Force Reconcile"** ➔ The Reconciliation Service actively queries the mock insurer's status endpoint, syncs the state, and transitions the policy to `ISSUED`!

### Step 5: Test Scenario 4 — Duplicate Callback Idempotency (`duplicate`)
1. Create a request and select **`duplicate`**.
2. The insurer mock fires two callbacks with the exact same `event_id`.
3. Check the **Provider Events** table at the bottom:
   - Both events are logged, but the second one is acknowledged as a duplicate without creating duplicate policy numbers or altering the terminal `ISSUED` state.

---

## 📥 Phase-by-Phase Deep Dive

### Phase 1: Intake & Validation

```
[Agent Input] ──▶ Form Validation ──▶ Duplicate Lock Check ──▶ Pricing Engine ──▶ State: VALIDATED
```

#### 1. Validation Rules
The intake request is validated against strict domain business rules:
- `client_request_id`: Required string (max 50 chars).
- `agent_id`: Required string (max 50 chars).
- `customer_name`: Required string (max 255 chars).
- `date_of_birth`: Must be a valid date in the past (`< today`).
- `trip_start`: Must be a future date (`>= tomorrow`).
- `trip_end`: Must be on or after `trip_start`.
- `destination`: Must be either `DOMESTIC` or `INTERNATIONAL`.
- `plan_code`: Must be either `BASIC`, `PLUS`, or `PREMIUM`.
- `sum_insured`: Numeric between ₹1,000 and ₹10,000,000.

#### 2. Duplicate Request Prevention (Concurrency Lock)
To prevent network retries by client agents from creating duplicate insurance policies, PolicyFlow executes an atomic check with row-level locking:
```sql
SELECT * FROM policy_requests 
WHERE agent_id = ? AND client_request_id = ? 
FOR UPDATE;
```
- **If found**: The transaction immediately returns the existing record with HTTP `200 OK` ("Policy request already exists").
- **If new**: Proceeds to premium calculation and record creation.

#### 3. Deterministic Premium Calculation
$$\text{Premium} = \text{Base Daily Rate} \times \text{Travel Days} \times \text{Age Factor} \times \text{Destination Factor} \times \text{Coverage Factor}$$

```
Example: 10 Travel Days, Age 36, INTERNATIONAL destination, PLUS plan
- Travel Days: Dec 1 to Dec 10 = 10 days
- Age at Travel: 36 years ➔ Multiplier = 1.25
- Destination: INTERNATIONAL ➔ Multiplier = 1.80
- Plan: PLUS (Base Rate = ₹20.00/day, Coverage Multiplier = 1.25)

Calculation:
₹20.00 × 10 × 1.25 × 1.80 × 1.25 = ₹562.50
```

#### 4. Automatic State Initialization
1. Record inserted in database with status `RECEIVED`.
2. Initial audit record written: `from_state: null ➔ to_state: RECEIVED`.
3. Policy instantly transitions to `VALIDATED`:
   - Audit record written: `from_state: RECEIVED ➔ to_state: VALIDATED`.
   - Immutable snapshot of all multiplier components is saved.

---

### Phase 2: Submission to Insurer

```
[Status: VALIDATED] ──▶ Transition to SUBMITTED ──▶ Mock Insurer Call ──▶ Transition to PENDING_CONFIRMATION
```

1. **State Guard**: Only policies in `VALIDATED` (or retrying from `FAILED`) can be submitted. Submitting an already issued policy returns HTTP `422 Unprocessable Entity`.
2. **Transition to `SUBMITTED`**:
   - Status updated to `SUBMITTED`.
   - Audit trail logs: `VALIDATED ➔ SUBMITTED` with scenario metadata.
3. **Insurer Submission**:
   - `MockInsurerService` assigns a unique `provider_reference`: `PROV-YYYYMMDDHHMMSS-XXXXXXXX`.
   - An `issuance_attempts` entry is created (Attempt #1) logging the full request payload, response payload, and timestamps.
4. **Transition to `PENDING_CONFIRMATION`**:
   - Policy moves to `PENDING_CONFIRMATION`, waiting for the provider webhook or callback event.

---

### Phase 3: Callback Processing & Idempotency

```
[Provider Callback] ──▶ Check Event Idempotency ──▶ Update Provider Events ──▶ Transition to Final State
```

The mock insurer triggers a webhook callback to `/api/mock-insurer/callback` containing:
```json
{
  "provider_reference": "PROV-20261023100000-ABC12345",
  "event_type": "ISSUED",
  "event_id": "EVT-PROV-20261023100000-ABC12345-ISSUED",
  "policy_number": "POL-20261023-F9A1B2",
  "payload": { "status": "APPROVED" }
}
```

#### Idempotency Safeguard:
1. Every callback is logged into `provider_events` with the unique key `provider_event_id`.
2. If a callback with the same `event_id` is received again:
   - The system recognizes it has already been processed.
   - It responds with HTTP `200 OK` ("Duplicate callback - already processed").
   - **No state change occurs, and no new policy number is generated.**
3. On first execution:
   - For `ISSUED`: Policy transitions to `ISSUED`, stores policy number, and reaches a terminal state.
   - For `REJECTED`: Policy transitions to `FAILED` with error message.
   - For `TIMEOUT`: Policy remains in `PENDING_CONFIRMATION` or moves to `NEEDS_RECONCILIATION`.

---

### Phase 4: Failure Handling & Retry Workflow

```
[Status: FAILED] ──▶ Agent Initiates Retry ──▶ Transition to SUBMITTED ──▶ Attempt #2 Logged
```

1. **Terminal Failure Protection**: A failed policy cannot be modified or regressed.
2. **Authorized Retry**:
   - The state machine specifically permits `FAILED ➔ SUBMITTED`.
   - Can be triggered via the UI ("Retry Submission" button), the Filament Admin panel, or `POST /api/policy-requests/{id}/submit`.
   - Increments `attempt_count` (e.g. Attempt #2).
   - Insurer generates a new provider reference and processes the resubmission.

---

### Phase 5: Reconciliation of Stale Requests

```
[Status: PENDING_CONFIRMATION / NEEDS_RECONCILIATION] ──▶ Query Insurer Status ──▶ Resolve State
```

1. **Stale Detection**: Any request in `PENDING_CONFIRMATION` that has not received a callback within 30 minutes is considered stale.
2. **Reconciliation Logic** (`ReconciliationService`):
   - Queries `MockInsurerService::lookupStatus(provider_reference)`.
   - If insurer shows `ISSUED`: Policy transitions to `ISSUED` and generates a policy number.
   - If insurer shows `REJECTED`: Policy transitions to `FAILED`.
   - If insurer still has no record or timed out: Policy transitions to `NEEDS_RECONCILIATION`.
3. **Execution Modes**:
   - **Interactive UI**: Click **"Force Reconcile"** on the Policy Detail page.
   - **Filament Admin**: Click **"Reconcile"** on any record or select multiple records for **"Bulk Reconcile"**.
   - **API**: `POST /api/admin/reconcile` with `provider_reference` or `stale_minutes`.
   - **Automated Cron**: Scheduled via Laravel Scheduler `php artisan schedule:run`.

---

## 🗄️ Immutable Audit Trail Schema

Every action is permanently recorded across four relational tables:

```
┌─────────────────────────────────┐
│         policy_requests         │
│  - id, client_request_id        │
│  - status (7 states)            │
│  - calculated_premium           │
│  - policy_number, attempt_count │
└───────┬──────────────┬──────────┘
        │              │
        ▼              ▼
┌──────────────┐ ┌──────────────────────┐
│state_history │ │  issuance_attempts   │
│- from_state  │ │ - attempt_number     │
│- to_state    │ │ - request_payload    │
│- metadata    │ │ - response_payload   │
└──────────────┘ └──────────────────────┘
        │
        ▼
┌───────────────────────────────┐
│        provider_events        │
│ - provider_event_id (UNIQUE)  │
│ - event_type, processed_at    │
└───────────────────────────────┘
```

---

## 🧪 Terminal & cURL Command Reference

If you prefer testing via terminal, here is the full executable workflow:

```bash
# 1. Intake New Policy Request
curl -s -X POST http://localhost:8000/api/policy-requests \
  -H "Content-Type: application/json" \
  -d '{
    "client_request_id": "AGT-DEMO-001",
    "agent_id": "AGT-101",
    "customer_name": "Aarav Sharma",
    "date_of_birth": "1992-06-15",
    "destination": "INTERNATIONAL",
    "trip_start": "2026-11-15",
    "trip_end": "2026-11-25",
    "plan_code": "PREMIUM",
    "sum_insured": 1000000
  }' | jq

# 2. Submit with Success Scenario
curl -s -X POST http://localhost:8000/api/policy-requests/1/submit \
  -H "X-Mock-Scenario: success" | jq

# 3. View Full Policy Resource with Complete Audit Trail
curl -s -X GET http://localhost:8000/api/policy-requests/1 | jq

# 4. Trigger Reconciliation on Stale Requests
curl -s -X POST http://localhost:8000/api/admin/reconcile \
  -H "Content-Type: application/json" \
  -d '{"stale_minutes": 30}' | jq
```

---

## 📋 Comprehensive Automated Tests

To run the entire test suite covering all 5 mock scenarios, pricing formulas, and concurrency locks:

```bash
cd Backend
php artisan test
```

Expected result:
```
Tests:    53 passed (241 assertions)
Duration: 1.10s
```