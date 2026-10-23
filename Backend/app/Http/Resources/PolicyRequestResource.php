<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PolicyRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_request_id' => $this->client_request_id,
            'agent_id' => $this->agent_id,
            'customer_name' => $this->customer_name,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'destination' => $this->destination,
            'trip_start' => $this->trip_start?->format('Y-m-d'),
            'trip_end' => $this->trip_end?->format('Y-m-d'),
            'plan_code' => $this->plan_code,
            'sum_insured' => $this->sum_insured,
            'travel_days' => $this->travel_days,
            'age_at_travel' => $this->age_at_travel,
            'premium_breakdown' => [
                'base_daily_rate' => $this->base_daily_rate,
                'age_multiplier' => $this->age_multiplier,
                'destination_multiplier' => $this->destination_multiplier,
                'coverage_multiplier' => $this->coverage_multiplier,
            ],
            'calculated_premium' => $this->calculated_premium,
            'status' => $this->status,
            'provider_reference' => $this->provider_reference,
            'policy_number' => $this->policy_number,
            'attempt_count' => $this->attempt_count,
            'last_response' => $this->last_response,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'audit_trail' => $this->whenLoaded('stateHistory', function () {
                return $this->stateHistory->map(function ($history) {
                    return [
                        'from_state' => $history->from_state,
                        'to_state' => $history->to_state,
                        'metadata' => $history->metadata,
                        'created_at' => $history->created_at?->toISOString(),
                    ];
                });
            }),
        ];
    }
}