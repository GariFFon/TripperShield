<?php

namespace Tests\Feature;

use App\Models\PolicyRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DuplicateIntakeTest extends TestCase
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

    public function test_duplicate_intake_same_agent_and_client_request_id_returns_existing(): void
    {
        // First request
        $response1 = $this->postJson('/api/policy-requests', $this->validRequestData);
        $response1->assertStatus(201);
        $id1 = $response1->json('data.id');

        // Second request with same agent_id + client_request_id
        $response2 = $this->postJson('/api/policy-requests', $this->validRequestData);
        $response2->assertStatus(200); // 200 for safe repeat
        $id2 = $response2->json('data.id');

        // Should return the same request
        $this->assertEquals($id1, $id2);
        $this->assertEquals('Policy request already exists', $response2->json('message'));

        // Only one record in database
        $this->assertEquals(1, PolicyRequest::count());
    }

    public function test_concurrent_duplicate_requests_do_not_create_duplicates(): void
    {
        // Simulate concurrent requests by making multiple requests in sequence
        // In a real concurrent scenario, the database unique constraint + SELECT FOR UPDATE
        // would prevent race conditions

        $responses = [];
        for ($i = 0; $i < 5; $i++) {
            $responses[] = $this->postJson('/api/policy-requests', $this->validRequestData);
        }

        // First should be 201, rest should be 200
        $this->assertEquals(201, $responses[0]->status());
        for ($i = 1; $i < 5; $i++) {
            $this->assertEquals(200, $responses[$i]->status());
        }

        // All should return the same ID
        $firstId = $responses[0]->json('data.id');
        foreach ($responses as $response) {
            $this->assertEquals($firstId, $response->json('data.id'));
        }

        // Only one record in database
        $this->assertEquals(1, PolicyRequest::count());
    }

    public function test_different_agent_same_client_request_id_creates_new(): void
    {
        $this->postJson('/api/policy-requests', $this->validRequestData);

        $data = $this->validRequestData;
        $data['agent_id'] = 'AGENT-18'; // Different agent

        $response = $this->postJson('/api/policy-requests', $data);
        $response->assertStatus(201);

        $this->assertEquals(2, PolicyRequest::count());
    }

    public function test_same_agent_different_client_request_id_creates_new(): void
    {
        $this->postJson('/api/policy-requests', $this->validRequestData);

        $data = $this->validRequestData;
        $data['client_request_id'] = 'AGT-2026-0002'; // Different client request

        $response = $this->postJson('/api/policy-requests', $data);
        $response->assertStatus(201);

        $this->assertEquals(2, PolicyRequest::count());
    }

    public function test_database_unique_constraint_enforced(): void
    {
        // Directly test the database constraint
        PolicyRequest::create([
            'client_request_id' => 'AGT-2026-0001',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'Sample Customer',
            'date_of_birth' => '1998-04-10',
            'destination' => 'INTERNATIONAL',
            'trip_start' => '2026-11-10',
            'trip_end' => '2026-11-14',
            'plan_code' => 'PLUS',
            'sum_insured' => 300000,
            'travel_days' => 5,
            'age_at_travel' => 28,
            'base_daily_rate' => 20.00,
            'age_multiplier' => 1.00,
            'destination_multiplier' => 1.80,
            'coverage_multiplier' => 1.25,
            'calculated_premium' => 225.00,
            'status' => 'RECEIVED',
        ]);

        // Try to insert duplicate - should fail at database level
        $this->expectException(\Illuminate\Database\QueryException::class);

        PolicyRequest::create([
            'client_request_id' => 'AGT-2026-0001',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'Different Customer',
            'date_of_birth' => '1990-01-01',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-12-01',
            'trip_end' => '2026-12-05',
            'plan_code' => 'BASIC',
            'sum_insured' => 100000,
            'travel_days' => 5,
            'age_at_travel' => 36,
            'base_daily_rate' => 15.00,
            'age_multiplier' => 1.25,
            'destination_multiplier' => 1.00,
            'coverage_multiplier' => 1.00,
            'calculated_premium' => 93.75,
            'status' => 'RECEIVED',
        ]);
    }
}