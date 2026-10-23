<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PolicyRequestResource;
use App\Models\PolicyRequest;
use App\Models\ProviderEvent;
use App\Services\MockInsurerService;
use App\Services\ReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MockInsurerController extends Controller
{
    public function __construct(
        private MockInsurerService $mockInsurer,
        private ReconciliationService $reconciliationService
    ) {}

    /**
     * Insurer callback endpoint.
     *
     * POST /api/mock-insurer/callback
     * Expected payload: { "provider_reference": "...", "event_type": "ISSUED|REJECTED|TIMEOUT", "policy_number": "..." }
     */
    public function callback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider_reference' => 'required|string',
            'event_type' => 'required|in:ISSUED,REJECTED,TIMEOUT',
            'policy_number' => 'nullable|string',
            'event_id' => 'nullable|string', // Optional idempotency key
        ]);

        // Find the policy request
        $policyRequest = PolicyRequest::where('provider_reference', $validated['provider_reference'])
            ->first();

        if (!$policyRequest) {
            return response()->json([
                'message' => 'Policy request not found for provider reference',
            ], 404);
        }

        // Idempotency check using provider_event_id if provided
        $eventId = $validated['event_id'] ?? 'EVT-' . $validated['provider_reference'] . '-' . $validated['event_type'];

        $existingEvent = ProviderEvent::where('provider_event_id', $eventId)->first();

        if ($existingEvent && $existingEvent->isProcessed()) {
            // Duplicate callback - return success but don't process again
            return response()->json([
                'message' => 'Duplicate callback - already processed',
                'event_id' => $eventId,
                'policy_number' => $policyRequest->policy_number,
                'status' => $policyRequest->status,
            ], 200);
        }

        // Create or update provider event
        $event = ProviderEvent::updateOrCreate(
            ['provider_event_id' => $eventId],
            [
                'policy_request_id' => $policyRequest->id,
                'event_type' => $validated['event_type'],
                'payload' => $validated,
                'processed_at' => null, // Will be set after processing
            ]
        );

        // Process based on event type
        $scenario = $this->mapEventTypeToScenario($validated['event_type']);
        $result = $this->mockInsurer->processCallback($policyRequest, $scenario);

        $event->markProcessed();

        return response()->json([
            'message' => 'Callback processed successfully',
            'event_id' => $eventId,
            'policy_number' => $policyRequest->policy_number,
            'status' => $policyRequest->status,
        ]);
    }

    /**
     * Provider status lookup (for reconciliation).
     *
     * GET /api/mock-insurer/status/{providerReference}
     */
    public function status(string $providerReference): JsonResponse
    {
        $result = $this->mockInsurer->checkStatus($providerReference);

        $statusCode = $result['found'] ? 200 : 404;

        return response()->json($result, $statusCode);
    }

    /**
     * Manual reconciliation trigger.
     *
     * POST /api/admin/reconcile
     */
    public function reconcile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider_reference' => 'nullable|string',
            'stale_minutes' => 'nullable|integer|min:1|max:1440',
        ]);

        if (!empty($validated['provider_reference'] ?? null)) {
            // Force reconcile specific request
            $result = $this->reconciliationService->forceReconcile($validated['provider_reference']);
            return response()->json($result);
        }

        // Run reconciliation for all stale requests
        $staleMinutes = $validated['stale_minutes'] ?? 30;
        $result = $this->reconciliationService->runReconciliation($staleMinutes);

        return response()->json($result);
    }

    /**
     * Get available mock scenarios.
     *
     * GET /api/mock-insurer/scenarios
     */
    public function scenarios(): JsonResponse
    {
        return response()->json([
            'scenarios' => $this->mockInsurer->getScenarios(),
        ]);
    }

    /**
     * Simulate duplicate callback for testing.
     *
     * POST /api/mock-insurer/test/duplicate-callback
     */
    public function testDuplicateCallback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'policy_request_id' => 'required|exists:policy_requests,id',
        ]);

        $policyRequest = PolicyRequest::findOrFail($validated['policy_request_id']);
        $result = $this->mockInsurer->simulateDuplicateCallback($policyRequest);

        return response()->json($result);
    }

    /**
     * Map event type to scenario.
     */
    private function mapEventTypeToScenario(string $eventType): string
    {
        return match ($eventType) {
            'ISSUED' => 'success',
            'REJECTED' => 'failure',
            'TIMEOUT' => 'timeout',
            default => 'success',
        };
    }
}