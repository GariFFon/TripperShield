<?php

namespace Tests\Feature;

use App\Models\PolicyRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyRequestTest extends TestCase
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

    public function test_valid_request_produces_expected_premium_and_status(): void
    {
        $response = $this->postJson('/api/policy-requests', $this->validRequestData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'client_request_id',
                    'agent_id',
                    'customer_name',
                    'destination',
                    'plan_code',
                    'calculated_premium',
                    'status',
                    'policy_number',
                    'audit_trail',
                ],
            ]);

        $this->assertEquals(225.00, $response->json('data.calculated_premium'));
        $this->assertEquals('VALIDATED', $response->json('data.status'));

        // Verify in database
        $request = PolicyRequest::where('client_request_id', 'AGT-2026-0001')
            ->where('agent_id', 'AGENT-17')
            ->first();

        $this->assertNotNull($request);
        $this->assertEquals(225.00, $request->calculated_premium);
        $this->assertEquals('VALIDATED', $request->status);
    }

    public function test_invalid_travel_dates_rejected(): void
    {
        $data = $this->validRequestData;
        $data['trip_start'] = '2026-11-14';
        $data['trip_end'] = '2026-11-10'; // End before start

        $response = $this->postJson('/api/policy-requests', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('trip_end');
    }

    public function test_unsupported_plan_rejected(): void
    {
        $data = $this->validRequestData;
        $data['plan_code'] = 'INVALID_PLAN';

        $response = $this->postJson('/api/policy-requests', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('plan_code');
    }

    public function test_unsupported_destination_rejected(): void
    {
        $data = $this->validRequestData;
        $data['destination'] = 'MARS';

        $response = $this->postJson('/api/policy-requests', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('destination');
    }

    public function test_missing_required_fields_rejected(): void
    {
        $response = $this->postJson('/api/policy-requests', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'client_request_id',
                'agent_id',
                'customer_name',
                'date_of_birth',
                'destination',
                'trip_start',
                'trip_end',
                'plan_code',
                'sum_insured',
            ]);
    }

    public function test_repeated_agent_client_request_id_does_not_create_another_logical_request(): void
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

        // Only one record in database
        $this->assertEquals(1, PolicyRequest::count());
    }

    public function test_repeated_request_returns_existing_resource(): void
    {
        $this->postJson('/api/policy-requests', $this->validRequestData);

        $response = $this->postJson('/api/policy-requests', $this->validRequestData);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Policy request already exists',
            ]);
    }

    public function test_different_agent_same_client_request_id_creates_new_request(): void
    {
        $this->postJson('/api/policy-requests', $this->validRequestData);

        $data = $this->validRequestData;
        $data['agent_id'] = 'AGENT-18'; // Different agent

        $response = $this->postJson('/api/policy-requests', $data);

        $response->assertStatus(201);
        $this->assertEquals(2, PolicyRequest::count());
    }

    public function test_get_policy_request_returns_audit_trail(): void
    {
        $response = $this->postJson('/api/policy-requests', $this->validRequestData);
        $id = $response->json('data.id');

        $getResponse = $this->getJson("/api/policy-requests/{$id}");

        $getResponse->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'audit_trail' => [
                        '*' => ['from_state', 'to_state', 'metadata', 'created_at'],
                    ],
                ],
            ]);

        $auditTrail = $getResponse->json('data.audit_trail');
        $this->assertCount(2, $auditTrail); // RECEIVED -> VALIDATED
        $this->assertEquals('RECEIVED', $auditTrail[0]['to_state']);
        $this->assertEquals('VALIDATED', $auditTrail[1]['to_state']);
    }

    public function test_list_policy_requests_with_filters(): void
    {
        $this->postJson('/api/policy-requests', $this->validRequestData);

        $data2 = $this->validRequestData;
        $data2['client_request_id'] = 'AGT-2026-0002';
        $data2['agent_id'] = 'AGENT-18';
        $this->postJson('/api/policy-requests', $data2);

        // Filter by agent
        $response = $this->getJson('/api/policy-requests?agent_id=AGENT-17');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));

        // Filter by status
        $response = $this->getJson('/api/policy-requests?status=VALIDATED');
        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_past_date_of_birth_required(): void
    {
        $data = $this->validRequestData;
        $data['date_of_birth'] = '2030-01-01'; // Future date

        $response = $this->postJson('/api/policy-requests', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('date_of_birth');
    }

    public function test_future_trip_start_required(): void
    {
        $data = $this->validRequestData;
        $data['trip_start'] = '2020-01-01'; // Past date

        $response = $this->postJson('/api/policy-requests', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('trip_start');
    }

    public function test_sum_insured_bounds(): void
    {
        // Too low
        $data = $this->validRequestData;
        $data['sum_insured'] = 500;

        $response = $this->postJson('/api/policy-requests', $data);
        $response->assertStatus(422)
            ->assertJsonValidationErrors('sum_insured');

        // Too high
        $data['sum_insured'] = 20000000;
        $response = $this->postJson('/api/policy-requests', $data);
        $response->assertStatus(422)
            ->assertJsonValidationErrors('sum_insured');
    }
}