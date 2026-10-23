<?php

namespace Tests\Feature;

use App\Models\PolicyRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconciliationTest extends TestCase
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

    public function test_reconcile_timeout_scenario_marks_needs_reconciliation(): void
    {
        $response = $this->postJson('/api/policy-requests', $this->validRequestData);
        $id = $response->json('data.id');

        // Submit with timeout scenario (no callback)
        $this->postJson("/api/policy-requests/{$id}/submit", [], [
            'X-Mock-Scenario' => 'timeout',
        ]);

        $request = PolicyRequest::find($id);
        $this->assertEquals('SUBMITTED', $request->status);
        $this->assertNotNull($request->provider_reference);

        // Manually set to PENDING_CONFIRMATION to simulate time passing
        $request->update(['status' => 'PENDING_CONFIRMATION']);

        // Run reconciliation
        $reconcileResponse = $this->postJson('/api/admin/reconcile', [
            'provider_reference' => $request->provider_reference,
        ]);

        $reconcileResponse->assertStatus(200);

        $request->refresh();
        // Should be marked as NEEDS_RECONCILIATION for timeout scenario
        $this->assertEquals('NEEDS_RECONCILIATION', $request->status);
    }

    public function test_reconcile_success_scenario_updates_to_issued(): void
    {
        $response = $this->postJson('/api/policy-requests', $this->validRequestData);
        $id = $response->json('data.id');

        $this->postJson("/api/policy-requests/{$id}/submit", [], [
            'X-Mock-Scenario' => 'success',
        ]);

        $request = PolicyRequest::find($id);
        $providerRef = $request->provider_reference;

        // Simulate stale PENDING_CONFIRMATION (no callback received)
        $request->update(['status' => 'PENDING_CONFIRMATION']);

        // Run reconciliation for specific provider reference
        $reconcileResponse = $this->postJson('/api/admin/reconcile', [
            'provider_reference' => $providerRef,
        ]);

        $reconcileResponse->assertStatus(200);
        $this->assertEquals('updated_to_issued', $reconcileResponse->json('action'));

        $request->refresh();
        $this->assertEquals('ISSUED', $request->status);
        $this->assertNotNull($request->policy_number);
    }

    public function test_reconcile_failure_scenario_updates_to_failed(): void
    {
        $response = $this->postJson('/api/policy-requests', $this->validRequestData);
        $id = $response->json('data.id');

        $this->postJson("/api/policy-requests/{$id}/submit", [], [
            'X-Mock-Scenario' => 'failure',
        ]);

        $request = PolicyRequest::find($id);
        $providerRef = $request->provider_reference;

        // Simulate stale PENDING_CONFIRMATION
        $request->update(['status' => 'PENDING_CONFIRMATION']);

        // Run reconciliation
        $reconcileResponse = $this->postJson('/api/admin/reconcile', [
            'provider_reference' => $providerRef,
        ]);

        $reconcileResponse->assertStatus(200);
        $this->assertEquals('updated_to_failed', $reconcileResponse->json('action'));

        $request->refresh();
        $this->assertEquals('FAILED', $request->status);
    }

    public function test_bulk_reconcile_all_stale_requests(): void
    {
        // Create multiple requests with timeout scenario
        $ids = [];
        for ($i = 1; $i <= 3; $i++) {
            $data = $this->validRequestData;
            $data['client_request_id'] = "AGT-2026-" . sprintf('%04d', $i);

            $response = $this->postJson('/api/policy-requests', $data);
            $id = $response->json('data.id');
            $ids[] = $id;

            $this->postJson("/api/policy-requests/{$id}/submit", [], [
                'X-Mock-Scenario' => 'timeout',
            ]);
        }

        // Update all to PENDING_CONFIRMATION with old updated_at
        // Use query builder to avoid timestamp auto-update
        \App\Models\PolicyRequest::whereIn('id', $ids)
            ->update([
                'status' => 'PENDING_CONFIRMATION',
                'updated_at' => now()->subMinutes(2),
            ]);

        // Run bulk reconciliation
        $reconcileResponse = $this->postJson('/api/admin/reconcile', [
            'stale_minutes' => 1, // Consider all as stale (minimum 1 minute)
        ]);

        $reconcileResponse->assertStatus(200);
        $this->assertEquals(3, $reconcileResponse->json('total_checked'));
    }

    public function test_reconcile_unknown_provider_reference(): void
    {
        $reconcileResponse = $this->postJson('/api/admin/reconcile', [
            'provider_reference' => 'PROV-NONEXISTENT-123',
        ]);

        $reconcileResponse->assertStatus(200);
        $this->assertFalse($reconcileResponse->json('success'));
        $this->assertEquals('No request found for provider reference', $reconcileResponse->json('message'));
    }

    public function test_issued_request_not_affected_by_reconciliation(): void
    {
        $response = $this->postJson('/api/policy-requests', $this->validRequestData);
        $id = $response->json('data.id');

        $this->postJson("/api/policy-requests/{$id}/submit", [], [
            'X-Mock-Scenario' => 'success',
        ]);

        $request = PolicyRequest::find($id);
        $providerRef = $request->provider_reference;

        // Complete the flow to ISSUED
        $this->postJson('/api/mock-insurer/callback', [
            'provider_reference' => $providerRef,
            'event_type' => 'ISSUED',
            'event_id' => 'EVT-RECONCILE-001',
        ]);

        $request->refresh();
        $originalPolicyNumber = $request->policy_number;
        $this->assertEquals('ISSUED', $request->status);

        // Run reconciliation on already issued request
        $reconcileResponse = $this->postJson('/api/admin/reconcile', [
            'provider_reference' => $providerRef,
        ]);

        $reconcileResponse->assertStatus(200);
        $this->assertEquals('none', $reconcileResponse->json('action'));

        $request->refresh();
        $this->assertEquals('ISSUED', $request->status);
        $this->assertEquals($originalPolicyNumber, $request->policy_number);
    }

    public function test_failed_request_can_be_retried_after_reconciliation(): void
    {
        $response = $this->postJson('/api/policy-requests', $this->validRequestData);
        $id = $response->json('data.id');

        $this->postJson("/api/policy-requests/{$id}/submit", [], [
            'X-Mock-Scenario' => 'failure',
        ]);

        $request = PolicyRequest::find($id);
        $providerRef = $request->provider_reference;

        // Complete to FAILED
        $this->postJson('/api/mock-insurer/callback', [
            'provider_reference' => $providerRef,
            'event_type' => 'REJECTED',
            'event_id' => 'EVT-RETRY-001',
        ]);

        $request->refresh();
        $this->assertEquals('FAILED', $request->status);

        // Reconcile should not change FAILED state
        $reconcileResponse = $this->postJson('/api/admin/reconcile', [
            'provider_reference' => $providerRef,
        ]);

        $reconcileResponse->assertStatus(200);

        $request->refresh();
        $this->assertEquals('FAILED', $request->status);

        // But we can manually retry submission (simulating retry after temporary failure)
        $retryResponse = $this->postJson("/api/policy-requests/{$id}/submit", [], [
            'X-Mock-Scenario' => 'success',
        ]);

        $retryResponse->assertStatus(200);

        $request->refresh();
        $this->assertEquals('ISSUED', $request->status);
        $this->assertEquals(2, $request->attempt_count);
    }
}