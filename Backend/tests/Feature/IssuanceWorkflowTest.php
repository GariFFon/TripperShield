<?php

namespace Tests\Feature;

use App\Models\PolicyRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IssuanceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private array $validRequestData = [
        'client_request_id' => 'AGT-2026-0001',
        'agent_id' => 'AGENT-17',
        'customer_name' => 'Sample Customer',
        'date_of_birth' => '1998-04-10',
        'destination' => 'INTERNATIONAL',
        'trip_start' => '2026-11-10',
        'trip_end' => '2026-11-14',
        'plan_code' => 'PLUS',
        'sum_insured' => 300000,
    ];

    public function test_submit_validated_request_transitions_to_submitted(): void
    {
        $response = $this->postJson('/api/policy-requests', $this->validRequestData);
        $id = $response->json('data.id');

        $submitResponse = $this->postJson("/api/policy-requests/{$id}/submit");

        $submitResponse->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => ['status'],
                'provider_response' => ['provider_reference', 'status'],
            ]);

        // Now submit processes callback immediately and transitions to ISSUED for success scenario
        $this->assertEquals('ISSUED', $submitResponse->json('data.status'));
        $this->assertNotNull($submitResponse->json('provider_response.provider_reference'));
    }

    public function test_successful_provider_response_produces_exactly_one_policy_number(): void
    {
        $response = $this->postJson('/api/policy-requests', $this->validRequestData);
        $id = $response->json('data.id');

        // Submit with success scenario - callback is processed automatically
        $submitResponse = $this->postJson("/api/policy-requests/{$id}/submit", [], [
            'X-Mock-Scenario' => 'success',
        ]);

        $submitResponse->assertStatus(200);
        $this->assertEquals('ISSUED', $submitResponse->json('data.status'));
        $this->assertNotNull($submitResponse->json('data.policy_number'));
        $this->assertStringStartsWith('POL-', $submitResponse->json('data.policy_number'));

        // Verify in database
        $request = PolicyRequest::find($id);
        $this->assertEquals('ISSUED', $request->status);
        $this->assertNotNull($request->policy_number);
    }

    public function test_rejected_provider_response_transitions_to_failed(): void
    {
        $response = $this->postJson('/api/policy-requests', $this->validRequestData);
        $id = $response->json('data.id');

        // Submit with failure scenario
        $submitResponse = $this->postJson("/api/policy-requests/{$id}/submit", [], [
            'X-Mock-Scenario' => 'failure',
        ]);

        $submitResponse->assertStatus(200);
        $this->assertEquals('FAILED', $submitResponse->json('data.status'));

        // Verify in database
        $request = PolicyRequest::find($id);
        $this->assertEquals('FAILED', $request->status);
        $this->assertNull($request->policy_number);
    }

    public function test_issued_state_is_terminal_cannot_regress(): void
    {
        $response = $this->postJson('/api/policy-requests', $this->validRequestData);
        $id = $response->json('data.id');

        $this->postJson("/api/policy-requests/{$id}/submit", [], [
            'X-Mock-Scenario' => 'success',
        ]);

        $request = PolicyRequest::find($id);
        $this->assertEquals('ISSUED', $request->status);

        // Try to submit again - should fail
        $submitResponse = $this->postJson("/api/policy-requests/{$id}/submit");
        $submitResponse->assertStatus(422);
        $this->assertEquals('ISSUED', $request->fresh()->status);
    }

    public function test_invalid_state_transition_rejected(): void
    {
        $response = $this->postJson('/api/policy-requests', $this->validRequestData);
        $id = $response->json('data.id');

        // Request is in VALIDATED state, try to submit
        $submitResponse = $this->postJson("/api/policy-requests/{$id}/submit");
        $submitResponse->assertStatus(200);

        // Try to submit again from ISSUED state - should fail
        $submitResponse2 = $this->postJson("/api/policy-requests/{$id}/submit");
        $submitResponse2->assertStatus(422);
    }

    public function test_issuance_attempt_recorded(): void
    {
        $response = $this->postJson('/api/policy-requests', $this->validRequestData);
        $id = $response->json('data.id');

        $this->postJson("/api/policy-requests/{$id}/submit", [], [
            'X-Mock-Scenario' => 'success',
        ]);

        $request = PolicyRequest::find($id);
        $this->assertEquals(1, $request->attempt_count);
        $this->assertCount(1, $request->issuanceAttempts);

        $attempt = $request->issuanceAttempts->first();
        $this->assertEquals(1, $attempt->attempt_number);
        $this->assertNotNull($attempt->response_payload);
    }

    public function test_provider_status_lookup(): void
    {
        $response = $this->postJson('/api/policy-requests', $this->validRequestData);
        $id = $response->json('data.id');

        $this->postJson("/api/policy-requests/{$id}/submit", [], [
            'X-Mock-Scenario' => 'success',
        ]);

        $request = PolicyRequest::find($id);
        $providerRef = $request->provider_reference;

        $statusResponse = $this->getJson("/api/mock-insurer/status/{$providerRef}");

        $statusResponse->assertStatus(200)
            ->assertJsonStructure([
                'found',
                'provider_reference',
                'policy_number',
                'status',
                'provider_status',
                'scenario',
            ]);

        $this->assertTrue($statusResponse->json('found'));
        $this->assertEquals('success', $statusResponse->json('scenario'));
    }

    public function test_scenarios_endpoint(): void
    {
        $response = $this->getJson('/api/mock-insurer/scenarios');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'scenarios' => [
                    'success',
                    'delayed',
                    'failure',
                    'duplicate',
                    'timeout',
                ],
            ]);
    }

    public function test_timeout_scenario_requires_reconciliation(): void
    {
        $response = $this->postJson('/api/policy-requests', $this->validRequestData);
        $id = $response->json('data.id');

        // Submit with timeout scenario - no callback processed
        $submitResponse = $this->postJson("/api/policy-requests/{$id}/submit", [], [
            'X-Mock-Scenario' => 'timeout',
        ]);

        $submitResponse->assertStatus(200);
        // Timeout scenario doesn't transition to PENDING_CONFIRMATION
        $this->assertEquals('SUBMITTED', $submitResponse->json('data.status'));

        $request = PolicyRequest::find($id);
        $this->assertEquals('SUBMITTED', $request->status);
    }

    public function test_duplicate_scenario_processes_callback_twice_but_idempotent(): void
    {
        $response = $this->postJson('/api/policy-requests', $this->validRequestData);
        $id = $response->json('data.id');

        // Submit with duplicate scenario
        $submitResponse = $this->postJson("/api/policy-requests/{$id}/submit", [], [
            'X-Mock-Scenario' => 'duplicate',
        ]);

        $submitResponse->assertStatus(200);
        $this->assertEquals('ISSUED', $submitResponse->json('data.status'));

        $request = PolicyRequest::find($id);
        $originalPolicyNumber = $request->policy_number;

        // Try to call callback endpoint with same event - should be idempotent
        $providerRef = $request->provider_reference;
        $callbackResponse = $this->postJson('/api/mock-insurer/callback', [
            'provider_reference' => $providerRef,
            'event_type' => 'ISSUED',
            'event_id' => 'EVT-' . $providerRef . '-ISSUED',
        ]);

        $callbackResponse->assertStatus(200);
        $this->assertStringContainsString('Duplicate callback', $callbackResponse->json('message'));

        $request->refresh();
        // Policy number should be the same
        $this->assertEquals($originalPolicyNumber, $request->policy_number);
    }
}