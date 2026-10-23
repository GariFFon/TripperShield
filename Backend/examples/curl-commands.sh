#!/bin/bash

# PolicyFlow API Example Commands
# Base URL for local development
BASE_URL="http://localhost:8000/api"

echo "=== PolicyFlow API Examples ==="
echo ""

# 1. Create a new policy request
echo "1. Create a new policy request (should return 201):"
curl -X POST "$BASE_URL/policy-requests" \
  -H "Content-Type: application/json" \
  -d '{
    "client_request_id": "AGT-2026-0001",
    "agent_id": "AGENT-17",
    "customer_name": "Sample Customer",
    "date_of_birth": "1998-04-10",
    "destination": "INTERNATIONAL",
    "trip_start": "2026-11-10",
    "trip_end": "2026-11-14",
    "plan_code": "PLUS",
    "sum_insured": 300000
  }'
echo ""
echo ""

# 2. Submit duplicate request (should return 200)
echo "2. Submit duplicate request (should return 200 with same ID):"
curl -X POST "$BASE_URL/policy-requests" \
  -H "Content-Type: application/json" \
  -d '{
    "client_request_id": "AGT-2026-0001",
    "agent_id": "AGENT-17",
    "customer_name": "Sample Customer",
    "date_of_birth": "1998-04-10",
    "destination": "INTERNATIONAL",
    "trip_start": "2026-11-10",
    "trip_end": "2026-11-14",
    "plan_code": "PLUS",
    "sum_insured": 300000
  }'
echo ""
echo ""

# 3. Invalid travel dates (should return 422)
echo "3. Invalid travel dates - trip_end before trip_start (should return 422):"
curl -X POST "$BASE_URL/policy-requests" \
  -H "Content-Type: application/json" \
  -d '{
    "client_request_id": "AGT-2026-0002",
    "agent_id": "AGENT-17",
    "customer_name": "Sample Customer",
    "date_of_birth": "1998-04-10",
    "destination": "INTERNATIONAL",
    "trip_start": "2026-11-14",
    "trip_end": "2026-11-10",
    "plan_code": "PLUS",
    "sum_insured": 300000
  }'
echo ""
echo ""

# 4. Unsupported plan (should return 422)
echo "4. Unsupported plan code (should return 422):"
curl -X POST "$BASE_URL/policy-requests" \
  -H "Content-Type: application/json" \
  -d '{
    "client_request_id": "AGT-2026-0003",
    "agent_id": "AGENT-17",
    "customer_name": "Sample Customer",
    "date_of_birth": "1998-04-10",
    "destination": "INTERNATIONAL",
    "trip_start": "2026-11-10",
    "trip_end": "2026-11-14",
    "plan_code": "INVALID_PLAN",
    "sum_insured": 300000
  }'
echo ""
echo ""

# 5. Get policy request with audit trail
echo "5. Get policy request with audit trail (replace {ID} with actual ID):"
curl -X GET "$BASE_URL/policy-requests/{ID}"
echo ""
echo ""

# 6. Submit to insurer with success scenario
echo "6. Submit to insurer with success scenario (replace {ID} with actual ID):"
curl -X POST "$BASE_URL/policy-requests/{ID}/submit" \
  -H "X-Mock-Scenario: success"
echo ""
echo ""

# 7. Submit to insurer with failure scenario
echo "7. Submit to insurer with failure scenario (replace {ID} with actual ID):"
curl -X POST "$BASE_URL/policy-requests/{ID}/submit" \
  -H "X-Mock-Scenario: failure"
echo ""
echo ""

# 8. Insurer callback - ISSUED
echo "8. Insurer callback - ISSUED (replace {PROVIDER_REF} with actual reference):"
curl -X POST "$BASE_URL/mock-insurer/callback" \
  -H "Content-Type: application/json" \
  -d '{
    "provider_reference": "{PROVIDER_REF}",
    "event_type": "ISSUED",
    "event_id": "EVT-001"
  }'
echo ""
echo ""

# 9. Insurer callback - REJECTED
echo "9. Insurer callback - REJECTED (replace {PROVIDER_REF} with actual reference):"
curl -X POST "$BASE_URL/mock-insurer/callback" \
  -H "Content-Type: application/json" \
  -d '{
    "provider_reference": "{PROVIDER_REF}",
    "event_type": "REJECTED",
    "event_id": "EVT-002"
  }'
echo ""
echo ""

# 10. Provider status lookup
echo "10. Provider status lookup (replace {PROVIDER_REF} with actual reference):"
curl -X GET "$BASE_URL/mock-insurer/status/{PROVIDER_REF}"
echo ""
echo ""

# 11. Get available scenarios
echo "11. Get available mock scenarios:"
curl -X GET "$BASE_URL/mock-insurer/scenarios"
echo ""
echo ""

# 12. Manual reconciliation
echo "12. Manual reconciliation (replace {PROVIDER_REF} with actual reference):"
curl -X POST "$BASE_URL/admin/reconcile" \
  -H "Content-Type: application/json" \
  -d '{
    "provider_reference": "{PROVIDER_REF}"
  }'
echo ""
echo ""

# 13. Bulk reconciliation for all stale requests
echo "13. Bulk reconciliation for all stale requests:"
curl -X POST "$BASE_URL/admin/reconcile" \
  -H "Content-Type: application/json" \
  -d '{
    "stale_minutes": 30
  }'
echo ""
echo ""

# 14. List all policy requests
echo "14. List all policy requests with filters:"
curl -X GET "$BASE_URL/policy-requests?status=VALIDATED&agent_id=AGENT-17"
echo ""
echo ""

echo "=== End of Examples ==="