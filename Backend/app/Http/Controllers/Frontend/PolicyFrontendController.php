<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\PolicyRequest;
use App\Services\PremiumCalculator;
use App\Services\MockInsurerService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PolicyFrontendController extends Controller
{
    public function __construct(
        private PremiumCalculator $premiumCalculator,
        private MockInsurerService $mockInsurer
    ) {}

    /**
     * Landing page
     */
    public function index()
    {
        return view('frontend.index');
    }

    /**
     * Show policy request form
     */
    public function create()
    {
        $demoData = [
            'client_request_id' => 'AGT-2026-' . rand(1000, 9999),
            'agent_id' => 'AGENT-' . rand(10, 99),
            'customer_name' => 'John Doe',
            'date_of_birth' => '1990-05-15',
            'destination' => 'INTERNATIONAL',
            'trip_start' => now()->addDays(10)->format('Y-m-d'),
            'trip_end' => now()->addDays(20)->format('Y-m-d'),
            'plan_code' => 'PLUS',
            'sum_insured' => 500000,
        ];

        return view('frontend.create', compact('demoData'));
    }

    /**
     * Store policy request from frontend form
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_request_id' => 'required|string|max:50',
            'agent_id' => 'required|string|max:50',
            'customer_name' => 'required|string|max:255',
            'date_of_birth' => 'required|date|before:today',
            'destination' => 'required|in:DOMESTIC,INTERNATIONAL',
            'trip_start' => 'required|date|after:today',
            'trip_end' => 'required|date|after_or_equal:trip_start',
            'plan_code' => 'required|in:BASIC,PLUS,PREMIUM',
            'sum_insured' => 'required|numeric|between:1000,10000000',
        ]);

        try {
            return DB::transaction(function () use ($validated) {
                $existing = PolicyRequest::where('agent_id', $validated['agent_id'])
                    ->where('client_request_id', $validated['client_request_id'])
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Policy request already exists (duplicate prevented)',
                        'data' => $this->formatPolicyResponse($existing->load('stateHistory')),
                        'duplicate' => true,
                        'redirect' => route('frontend.show', $existing->id),
                    ], 200);
                }

                $premiumData = $this->premiumCalculator->calculate($validated);

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

                $policyRequest->stateHistory()->create([
                    'from_state' => null,
                    'to_state' => 'RECEIVED',
                    'metadata' => ['created_via' => 'frontend'],
                ]);

                $policyRequest->transitionTo('VALIDATED', [
                    'reason' => 'All validations passed',
                    'premium_calculated' => $premiumData['calculated_premium'],
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Policy request created successfully',
                    'data' => $this->formatPolicyResponse($policyRequest->load('stateHistory')),
                    'redirect' => route('frontend.show', $policyRequest->id),
                ], 201);
            });
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * List all policy requests (frontend dashboard)
     */
    public function dashboard(Request $request)
    {
        $query = PolicyRequest::query()->with('stateHistory')->latest();

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }
        if ($request->has('agent_id') && $request->agent_id) {
            $query->where('agent_id', $request->agent_id);
        }
        if ($request->has('destination') && $request->destination) {
            $query->where('destination', $request->destination);
        }
        if ($request->has('plan_code') && $request->plan_code) {
            $query->where('plan_code', $request->plan_code);
        }
        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $requests = $query->paginate(10)->withQueryString();

        $stats = [
            'total' => PolicyRequest::count(),
            'validated' => PolicyRequest::where('status', 'VALIDATED')->count(),
            'submitted' => PolicyRequest::where('status', 'SUBMITTED')->count(),
            'pending' => PolicyRequest::where('status', 'PENDING_CONFIRMATION')->count(),
            'issued' => PolicyRequest::where('status', 'ISSUED')->count(),
            'failed' => PolicyRequest::where('status', 'FAILED')->count(),
            'needs_reconciliation' => PolicyRequest::where('status', 'NEEDS_RECONCILIATION')->count(),
        ];

        return view('frontend.dashboard', compact('requests', 'stats'));
    }

    /**
     * Show policy request detail
     */
    public function show(string $id)
    {
        $policyRequest = PolicyRequest::with([
            'stateHistory',
            'issuanceAttempts',
            'providerEvents',
        ])->findOrFail($id);

        return view('frontend.show', compact('policyRequest'));
    }

    /**
     * Submit to insurer from frontend
     */
    public function submit(Request $request, string $id): JsonResponse
    {
        $policyRequest = PolicyRequest::findOrFail($id);

        if (!$policyRequest->canTransitionTo('SUBMITTED')) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid state transition',
                'current_status' => $policyRequest->status,
            ], 422);
        }

        $scenario = $request->input('scenario', 'success');

        $policyRequest->transitionTo('SUBMITTED', [
            'scenario' => $scenario,
        ]);

        $result = $this->mockInsurer->submit($policyRequest, $scenario);

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

        if (in_array($scenario, ['success', 'failure', 'duplicate'])) {
            $providerRef = $result['provider_reference'];
            $eventType = $scenario === 'failure' ? 'REJECTED' : 'ISSUED';
            $eventId = 'EVT-' . $providerRef . '-' . $eventType;

            $policyRequest->transitionTo('PENDING_CONFIRMATION', [
                'provider_reference' => $providerRef,
                'scenario' => $scenario,
            ]);

            $this->mockInsurer->processCallback($policyRequest, $scenario, $eventId);
        }

        $policyRequest->refresh()->load(['stateHistory', 'issuanceAttempts']);

        return response()->json([
            'success' => true,
            'message' => 'Request submitted to insurer',
            'data' => $this->formatPolicyResponse($policyRequest),
            'provider_response' => $result,
        ]);
    }

    /**
     * Trigger reconciliation from frontend
     */
    public function reconcile(Request $request, string $id): JsonResponse
    {
        $policyRequest = PolicyRequest::findOrFail($id);

        if (!in_array($policyRequest->status, ['PENDING_CONFIRMATION', 'NEEDS_RECONCILIATION'])) {
            return response()->json([
                'success' => false,
                'message' => 'Reconciliation only available for PENDING_CONFIRMATION or NEEDS_RECONCILIATION',
            ], 422);
        }

        if (!$policyRequest->provider_reference) {
            return response()->json([
                'success' => false,
                'message' => 'No provider reference found for reconciliation',
            ], 422);
        }

        $mockInsurer = $this->mockInsurer;
        $status = $mockInsurer->checkStatus($policyRequest->provider_reference);

        if (!$status['found']) {
            return response()->json([
                'success' => false,
                'message' => 'No request found for this provider reference',
            ], 404);
        }

        $providerStatus = $status['provider_status'];
        $currentStatus = $policyRequest->status;
        $action = 'none';

        if ($providerStatus === 'ISSUED' && $currentStatus === 'PENDING_CONFIRMATION') {
            $result = $mockInsurer->processCallback($policyRequest, 'success');
            $action = 'updated_to_issued';
        } elseif ($providerStatus === 'REJECTED' && $currentStatus === 'PENDING_CONFIRMATION') {
            $result = $mockInsurer->processCallback($policyRequest, 'failure');
            $action = 'updated_to_failed';
        } elseif ($providerStatus === 'TIMEOUT' && $currentStatus === 'PENDING_CONFIRMATION') {
            $policyRequest->transitionTo('NEEDS_RECONCILIATION', [
                'reason' => 'Reconciliation check: Provider timeout',
            ]);
            $action = 'marked_needs_reconciliation';
        }

        $policyRequest->refresh()->load(['stateHistory', 'issuanceAttempts']);

        return response()->json([
            'success' => true,
            'message' => 'Reconciliation completed',
            'action' => $action,
            'data' => $this->formatPolicyResponse($policyRequest),
        ]);
    }

    /**
     * Retry submission for failed requests
     */
    public function retry(Request $request, string $id): JsonResponse
    {
        $policyRequest = PolicyRequest::findOrFail($id);

        if (!in_array($policyRequest->status, ['FAILED', 'NEEDS_RECONCILIATION'])) {
            return response()->json([
                'success' => false,
                'message' => 'Retry only available for FAILED or NEEDS_RECONCILIATION',
            ], 422);
        }

        $scenario = $request->input('scenario', 'success');

        $policyRequest->transitionTo('SUBMITTED', [
            'scenario' => $scenario,
            'retry' => true,
        ]);

        $result = $this->mockInsurer->submit($policyRequest, $scenario);

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

        if (in_array($scenario, ['success', 'failure', 'duplicate'])) {
            $providerRef = $result['provider_reference'];
            $eventType = $scenario === 'failure' ? 'REJECTED' : 'ISSUED';
            $eventId = 'EVT-' . $providerRef . '-' . $eventType;

            $policyRequest->transitionTo('PENDING_CONFIRMATION', [
                'provider_reference' => $providerRef,
                'scenario' => $scenario,
            ]);

            $this->mockInsurer->processCallback($policyRequest, $scenario, $eventId);
        }

        $policyRequest->refresh()->load(['stateHistory', 'issuanceAttempts']);

        return response()->json([
            'success' => true,
            'message' => 'Retry submitted successfully',
            'data' => $this->formatPolicyResponse($policyRequest),
            'provider_response' => $result,
        ]);
    }

    /**
     * Get premium calculation preview (AJAX)
     */
    public function calculatePremium(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_code' => 'required|in:BASIC,PLUS,PREMIUM',
            'destination' => 'required|in:DOMESTIC,INTERNATIONAL',
            'date_of_birth' => 'required|date',
            'trip_start' => 'required|date',
            'trip_end' => 'required|date|after_or_equal:trip_start',
        ]);

        try {
            $result = $this->premiumCalculator->calculate($validated);
            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Format policy request for frontend response
     */
    private function formatPolicyResponse(PolicyRequest $request): array
    {
        return [
            'id' => $request->id,
            'client_request_id' => $request->client_request_id,
            'agent_id' => $request->agent_id,
            'customer_name' => $request->customer_name,
            'date_of_birth' => $request->date_of_birth?->format('Y-m-d'),
            'destination' => $request->destination,
            'trip_start' => $request->trip_start?->format('Y-m-d'),
            'trip_end' => $request->trip_end?->format('Y-m-d'),
            'plan_code' => $request->plan_code,
            'sum_insured' => $request->sum_insured,
            'travel_days' => $request->travel_days,
            'age_at_travel' => $request->age_at_travel,
            'premium_breakdown' => [
                'base_daily_rate' => $request->base_daily_rate,
                'age_multiplier' => $request->age_multiplier,
                'destination_multiplier' => $request->destination_multiplier,
                'coverage_multiplier' => $request->coverage_multiplier,
            ],
            'calculated_premium' => $request->calculated_premium,
            'status' => $request->status,
            'provider_reference' => $request->provider_reference,
            'policy_number' => $request->policy_number,
            'attempt_count' => $request->attempt_count,
            'last_response' => $request->last_response,
            'created_at' => $request->created_at?->toISOString(),
            'updated_at' => $request->updated_at?->toISOString(),
            'audit_trail' => $request->stateHistory->map(function ($history) {
                return [
                    'from_state' => $history->from_state,
                    'to_state' => $history->to_state,
                    'metadata' => $history->metadata,
                    'created_at' => $history->created_at?->toISOString(),
                ];
            })->values()->toArray(),
            'issuance_attempts' => $request->issuanceAttempts->map(function ($attempt) {
                return [
                    'attempt_number' => $attempt->attempt_number,
                    'response_status' => $attempt->response_status,
                    'response_payload' => $attempt->response_payload,
                    'started_at' => $attempt->started_at?->toISOString(),
                    'completed_at' => $attempt->completed_at?->toISOString(),
                ];
            })->values()->toArray(),
        ];
    }
}