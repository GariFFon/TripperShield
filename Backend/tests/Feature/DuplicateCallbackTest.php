<?php

namespace Tests\Feature;

use App\Models\PolicyRequest;
use App\Models\ProviderEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DuplicateCallbackTest extends TestCase
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

    public function test_duplicate_callback_is_idempotent(): void
    {
        // Create and submit request
        $response = $this->postJson('/api/policy-requests', $this->validRequestData);
        $id = $response->json('data.id');

        $this->postJson("/api/policy-requests/{$id}/submit", [], [
            'X-Mock-Scenario' => 'duplicate',
        ]);

        $request = PolicyRequest::find($id);
        $providerRef = $request->provider_reference;

        // First callback
        $callback1 = $this->postJson('/api/mock-insurer/callback', [
            'provider_reference' => $providerRef,
            'event_type' => 'ISSUED',
            'event_id' => 'EVT-DUPLICATE-TEST-001',
        ]);
        $callback1->assertStatus(200);

        $request->refresh();
        $policyNumber1 = $request->policy_number;
        $this->assertEquals('ISSUED', $request->status);
        $this->assertNotNull($policyNumber1);

        // Second duplicate callback with same event_id (idempotency key)
        $callback2 = $this->postJson('/api/mock-insurer/callback', [
            'provider_reference' => $providerRef,
            'event_type' => 'ISSUED',
            'event_id' => 'EVT-DUPLICATE-TEST-001', // Same event_id
        ]);
        $callback2->assertStatus(200);

        // Should return duplicate message but not change state
        $this->assertStringContainsString('Duplicate callback', $callback2->json('message'));

        $request->refresh();
        $policyNumber2 = $request->policy_number;

        // Policy number should be the same (not changed)
        $this->assertEquals($policyNumber1, $policyNumber2);
        $this->assertEquals('ISSUED', $request->status);
    }

    public function test_duplicate_callback_without_event_id_uses_provider_ref_and_type(): void
    {
        $response = $this->postJson('/api/policy-requests', $this->validRequestData);
        $id = $response->json('data.id');

        $this->postJson("/api/policy-requests/{$id}/submit", [], [
            'X-Mock-Scenario' => 'success',
        ]);

        $request = PolicyRequest::find($id);
        $providerRef = $request->provider_reference;

        // First callback
        $callback1 = $this->postJson('/api/mock-insurer/callback', [
            'provider_reference' => $providerRef,
            'event_type' => 'ISSUED',
            // No event_id provided - should use provider_ref + event_type as key
        ]);
        $callback1->assertStatus(200);

        $request->refresh();
        $policyNumber1 = $request->policy_number;

        // Second callback without event_id (should be treated as duplicate)
        $callback2 = $this->postJson('/api/mock-insurer/callback', [
            'provider_reference' => $providerRef,
            'event_type' => 'ISSUED',
        ]);
        $callback2->assertStatus(200);

        $this->assertStringContainsString('Duplicate callback', $callback2->json('message'));

        $request->refresh();
        $policyNumber2 = $request->policy_number;
        $this->assertEquals($policyNumber1, $policyNumber2);
    }

    public function test_repeated_callback_cannot_overwrite_confirmed_issued_state(): void
    {
        $response = $this->postJson('/api/policy-requests', $this->validRequestData);
        $id = $response->json('data.id');

        $this->postJson("/api/policy-requests/{$id}/submit", [], [
            'X-Mock-Scenario' => 'success',
        ]);

        $request = PolicyRequest::find($id);
        $providerRef = $request->provider_reference;

        // First callback - ISSUED
        $this->postJson('/api/mock-insurer/callback', [
            'provider_reference' => $providerRef,
            'event_type' => 'ISSUED',
            'event_id' => 'EVT-CONFIRM-001',
        ]);

        $request->refresh();
        $this->assertEquals('ISSUED', $request->status);
        $originalPolicyNumber = $request->policy_number;

        // Try to send REJECTED callback for same request (should not overwrite ISSUED)
        $callback = $this->postJson('/api/mock-insurer/callback', [
            'provider_reference' => $providerRef,
            'event_type' => 'REJECTED',
            'event_id' => 'EVT-CONFIRM-002',
        ]);
        $callback->assertStatus(200);

        $request->refresh();

        // State should remain ISSUED (terminal state)
        $this->assertEquals('ISSUED', $request->status);
        $this->assertEquals($originalPolicyNumber, $request->policy_number);
    }

    public function test_provider_event_recorded_for_each_callback(): void
    {
        $response = $this->postJson('/api/policy-requests', $this->validRequestData);
        $id = $response->json('data.id');

        $this->postJson("/api/policy-requests/{$id}/submit", [], [
            'X-Mock-Scenario' => 'success',
        ]);

        $request = PolicyRequest::find($id);
        $providerRef = $request->provider_reference;

        // First callback
        $this->postJson('/api/mock-insurer/callback', [
            'provider_reference' => $providerRef,
            'event_type' => 'ISSUED',
            'event_id' => 'EVT-RECORD-001',
        ]);

        // Second callback (duplicate)
        $this->postJson('/api/mock-insurer/callback', [
            'provider_reference' => $providerRef,
            'event_type' => 'ISSUED',
            'event_id' => 'EVT-RECORD-001', // Same event_id
        ]);

        // Should have only one ProviderEvent record with processed_at
        $events = ProviderEvent::where('provider_event_id', 'EVT-RECORD-001')->get();
        $this->assertCount(1, $events);
        $this->assertNotNull($events->first()->processed_at);
    }

    public function test_test_duplicate_callback_endpoint(): void
    {
        $response = $this->postJson('/api/policy-requests', $this->validRequestData);
        $id = $response->json('data.id');

        $this->postJson("/api/policy-requests/{$id}/submit", [], [
            'X-Mock-Scenario' => 'duplicate',
        ]);

        $request = PolicyRequest::find($id);

        // Use the test endpoint
        $testResponse = $this->postJson('/api/mock-insurer/test/duplicate-callback', [
            'policy_request_id' => $id,
        ]);

        $testResponse->assertStatus(200)
            ->assertJsonStructure([
                'first_callback',
                'second_callback',
                'policy_number',
                'status',
            ]);

        $request->refresh();
        // Should still be ISSUED with one policy number
        $this->assertEquals('ISSUED', $request->status);
    }
}