<?php

namespace App\Services;

use App\Models\PolicyRequest;
use Illuminate\Support\Str;

class MockInsurerService
{
    // Scenario configurations
    private const SCENARIOS = [
        'success' => [
            'description' => 'Immediate successful issuance',
            'delay' => 0,
            'result' => 'ISSUED',
            'policy_number' => true,
        ],
        'delayed' => [
            'description' => 'Delayed callback (simulates timeout)',
            'delay' => 2, // seconds
            'result' => 'ISSUED',
            'policy_number' => true,
        ],
        'failure' => [
            'description' => 'Rejected by insurer',
            'delay' => 0,
            'result' => 'REJECTED',
            'policy_number' => false,
        ],
        'duplicate' => [
            'description' => 'Sends duplicate ISSUED callback',
            'delay' => 0,
            'result' => 'ISSUED',
            'policy_number' => true,
            'duplicate_callback' => true,
        ],
        'timeout' => [
            'description' => 'No callback (requires reconciliation)',
            'delay' => 0,
            'result' => 'TIMEOUT',
            'policy_number' => false,
        ],
    ];

    /**
     * Submit a policy request to the mock insurer
     *
     * @param PolicyRequest $policyRequest
     * @param string $scenario
     * @return array Provider response
     */
    public function submit(PolicyRequest $policyRequest, string $scenario = 'success'): array
    {
        $config = self::SCENARIOS[$scenario] ?? self::SCENARIOS['success'];

        // Generate provider reference
        $providerReference = 'PROV-' . now()->format('YmdHis') . '-' . Str::random(8);

        // Store the scenario for later callback processing
        $policyRequest->update([
            'provider_reference' => $providerReference,
            'last_response' => [
                'scenario' => $scenario,
                'config' => $config,
                'submitted_at' => now()->toISOString(),
            ],
        ]);

        // Note: Callback processing is now handled by the controller after transitioning to PENDING_CONFIRMATION
        // For delayed scenario, we'd normally use a queue

        return [
            'provider_reference' => $providerReference,
            'status' => 'SUBMITTED',
            'scenario' => $scenario,
            'message' => $config['description'],
        ];
    }

    /**
     * Process insurer callback
     */
    public function processCallback(PolicyRequest $policyRequest, string $scenario = null, string $eventId = null): array
    {
        $lastResponse = $policyRequest->last_response ?? [];
        $scenario = $scenario ?? ($lastResponse['scenario'] ?? 'success');
        $config = self::SCENARIOS[$scenario] ?? self::SCENARIOS['success'];

        // Use provided event_id or generate one
        $providerEventId = $eventId ?? 'EVT-' . now()->format('YmdHis') . '-' . Str::random(8);

        // Check if already processed
        $existingEvent = \App\Models\ProviderEvent::where('provider_event_id', $providerEventId)->first();
        if ($existingEvent && $existingEvent->isProcessed()) {
            return [
                'event_id' => $providerEventId,
                'event_type' => $config['result'],
                'policy_number' => $policyRequest->policy_number,
                'status' => $policyRequest->status,
                'duplicate' => true,
            ];
        }

        // Create or update provider event record
        $event = \App\Models\ProviderEvent::updateOrCreate(
            ['provider_event_id' => $providerEventId],
            [
                'policy_request_id' => $policyRequest->id,
                'event_type' => $config['result'],
                'payload' => [
                    'scenario' => $scenario,
                    'policy_number' => $config['policy_number'] ? $policyRequest->generatePolicyNumber() : null,
                    'provider_reference' => $policyRequest->provider_reference,
                ],
                'processed_at' => null,
            ]
        );

        // Handle different results
        switch ($config['result']) {
            case 'ISSUED':
                $policyNumber = $config['policy_number'] ? $policyRequest->generatePolicyNumber() : null;
                if ($policyRequest->canTransitionTo('ISSUED')) {
                    $policyRequest->transitionTo('ISSUED', [
                        'provider_event_id' => $providerEventId,
                        'policy_number' => $policyNumber,
                    ]);
                    if ($policyNumber) {
                        $policyRequest->update(['policy_number' => $policyNumber]);
                    }
                }
                break;

            case 'REJECTED':
                if ($policyRequest->canTransitionTo('FAILED')) {
                    $policyRequest->transitionTo('FAILED', [
                        'provider_event_id' => $providerEventId,
                        'reason' => 'Rejected by insurer',
                    ]);
                }
                break;

            case 'TIMEOUT':
                if ($policyRequest->canTransitionTo('NEEDS_RECONCILIATION')) {
                    $policyRequest->transitionTo('NEEDS_RECONCILIATION', [
                        'provider_event_id' => $providerEventId,
                        'reason' => 'No response from insurer',
                    ]);
                }
                break;
        }

        $event->markProcessed();

        return [
            'event_id' => $providerEventId,
            'event_type' => $config['result'],
            'policy_number' => $policyRequest->policy_number,
            'status' => $policyRequest->status,
        ];
    }

    /**
     * Check provider status (for reconciliation)
     */
    public function checkStatus(string $providerReference): array
    {
        $policyRequest = PolicyRequest::where('provider_reference', $providerReference)->first();

        if (!$policyRequest) {
            return [
                'found' => false,
                'status' => 'UNKNOWN',
                'message' => 'No request found for this provider reference',
            ];
        }

        // Simulate provider lookup based on stored scenario
        $lastResponse = $policyRequest->last_response ?? [];
        $scenario = $lastResponse['scenario'] ?? 'success';
        $config = self::SCENARIOS[$scenario] ?? self::SCENARIOS['success'];

        return [
            'found' => true,
            'provider_reference' => $providerReference,
            'policy_number' => $policyRequest->policy_number,
            'status' => $policyRequest->status,
            'provider_status' => $config['result'],
            'scenario' => $scenario,
        ];
    }

    /**
     * Get all available scenarios
     */
    public function getScenarios(): array
    {
        return self::SCENARIOS;
    }

    /**
     * Simulate duplicate callback for testing idempotency
     */
    public function simulateDuplicateCallback(PolicyRequest $policyRequest): array
    {
        // First callback
        $result1 = $this->processCallback($policyRequest, 'duplicate');

        // Second duplicate callback (should be idempotent)
        $result2 = $this->processCallback($policyRequest, 'duplicate');

        return [
            'first_callback' => $result1,
            'second_callback' => $result2,
            'policy_number' => $policyRequest->policy_number,
            'status' => $policyRequest->status,
        ];
    }
}