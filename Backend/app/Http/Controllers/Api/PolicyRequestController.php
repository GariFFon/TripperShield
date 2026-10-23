<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePolicyRequestRequest;
use App\Http\Resources\PolicyRequestResource;
use App\Http\Resources\PolicyRequestCollection;
use App\Models\PolicyRequest;
use App\Services\PremiumCalculator;
use App\Services\MockInsurerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PolicyRequestController extends Controller
{
    public function __construct(
        private PremiumCalculator $premiumCalculator,
        private MockInsurerService $mockInsurer
    ) {}

    /**
     * Store a newly created policy request.
     *
     * POST /api/policy-requests
     * Returns 201 for new request, 200 for duplicate (same agent_id + client_request_id)
     */
    public function store(StorePolicyRequestRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Check for duplicate (same agent_id + client_request_id)
        // Use database transaction with lock to prevent race conditions
        return DB::transaction(function () use ($validated) {
            $existing = PolicyRequest::where('agent_id', $validated['agent_id'])
                ->where('client_request_id', $validated['client_request_id'])
                ->lockForUpdate()
                ->first();

            if ($existing) {
                // Return existing request with 200 OK (safe repeat)
                return response()->json([
                    'message' => 'Policy request already exists',
                    'data' => new PolicyRequestResource($existing->load('stateHistory')),
                ], 200);
            }

            // Calculate premium
            $premiumData = $this->premiumCalculator->calculate($validated);

            // Create new policy request
            $policyRequest = PolicyRequest::create([
                'client_request_id' => $validated['client_request_id'],
                'agent_id' => $validated['agent_id'],
                'customer_name' => $validated['customer_name'],
                'date_of_birth' => $validated['date_of_birth'],
                'destination' => $validated['destination'],
                'trip_start' => $validated['trip_start'],
                'trip_end' => $validated['trip_end'],
                'plan_code' => $validated['plan_code'],
                'sum_insured' => $validated['sum_insured'],
                'travel_days' => $premiumData['travel_days'],
                'age_at_travel' => $premiumData['age_at_travel'],
                'base_daily_rate' => $premiumData['base_daily_rate'],
                'age_multiplier' => $premiumData['age_multiplier'],
                'destination_multiplier' => $premiumData['destination_multiplier'],
                'coverage_multiplier' => $premiumData['coverage_multiplier'],
                'calculated_premium' => $premiumData['calculated_premium'],
                'status' => 'RECEIVED',
            ]);

            // Record initial state in history
            $policyRequest->stateHistory()->create([
                'from_state' => null,
                'to_state' => 'RECEIVED',
                'metadata' => ['created_via' => 'api'],
            ]);

            // Auto-transition to VALIDATED
            $policyRequest->transitionTo('VALIDATED', [
                'reason' => 'All validations passed',
                'premium_calculated' => $premiumData['calculated_premium'],
            ]);

            return response()->json([
                'message' => 'Policy request created successfully',
                'data' => new PolicyRequestResource($policyRequest->load('stateHistory')),
            ], 201);
        });
    }

    /**
     * Display the specified policy request with audit trail.
     *
     * GET /api/policy-requests/{id}
     */
    public function show(string $id): JsonResponse
    {
        $policyRequest = PolicyRequest::with('stateHistory', 'issuanceAttempts', 'providerEvents')
            ->findOrFail($id);

        return response()->json([
            'data' => new PolicyRequestResource($policyRequest),
        ]);
    }

    /**
     * Submit a validated request to the mock insurer.
     *
     * POST /api/policy-requests/{id}/submit
     */
    public function submit(Request $request, string $id): JsonResponse
    {
        $policyRequest = PolicyRequest::findOrFail($id);

        // Validate state transition
        if (!$policyRequest->canTransitionTo('SUBMITTED')) {
            return response()->json([
                'message' => 'Invalid state transition',
                'current_status' => $policyRequest->status,
                'allowed_transitions' => PolicyRequest::validTransitions()[$policyRequest->status] ?? [],
            ], 422);
        }

        // Get scenario from header or default to success
        $scenario = $request->header('X-Mock-Scenario', 'success');

        // Transition to SUBMITTED
        $policyRequest->transitionTo('SUBMITTED', [
            'scenario' => $scenario,
        ]);

        // Submit to mock insurer (doesn't process callback yet)
        $result = $this->mockInsurer->submit($policyRequest, $scenario);

        // Record issuance attempt
        $attemptNumber = $policyRequest->issuanceAttempts()->count() + 1;
        $policyRequest->issuanceAttempts()->create([
            'attempt_number' => $attemptNumber,
            'request_payload' => $policyRequest->toArray(),
            'response_payload' => $result,
            'response_status' => $result['status'],
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $policyRequest->increment('attempt_count');

        // Transition to PENDING_CONFIRMATION for immediate scenarios
        if (in_array($scenario, ['success', 'failure', 'duplicate'])) {
            $providerRef = $result['provider_reference'];
            $eventType = $scenario === 'failure' ? 'REJECTED' : 'ISSUED';
            $eventId = 'EVT-' . $providerRef . '-' . $eventType;

            $policyRequest->transitionTo('PENDING_CONFIRMATION', [
                'provider_reference' => $providerRef,
                'scenario' => $scenario,
            ]);

            // Now process the callback (will transition to ISSUED/FAILED)
            $this->mockInsurer->processCallback($policyRequest, $scenario, $eventId);
        }

        return response()->json([
            'message' => 'Request submitted to insurer',
            'data' => new PolicyRequestResource($policyRequest->load('stateHistory')),
            'provider_response' => $result,
        ]);
    }

    /**
     * List all policy requests (with pagination).
     *
     * GET /api/policy-requests
     */
    public function index(Request $request): JsonResponse
    {
        $query = PolicyRequest::query()
            ->with('stateHistory')
            ->latest();

        // Optional filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('agent_id')) {
            $query->where('agent_id', $request->agent_id);
        }
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $requests = $query->paginate($request->get('per_page', 15));

        // Use toArray to properly convert the ResourceCollection
        $collection = PolicyRequestResource::collection($requests);
        $collectionData = $collection->toArray($request);

        return response()->json([
            'data' => $collectionData,
            'meta' => [
                'total' => $requests->total(),
                'per_page' => $requests->perPage(),
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
            ],
        ]);
    }
}