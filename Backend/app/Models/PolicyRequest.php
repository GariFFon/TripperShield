<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PolicyRequest extends Model
{
    use HasFactory;

    protected $table = 'policy_requests';

    protected $fillable = [
        'client_request_id',
        'agent_id',
        'customer_name',
        'date_of_birth',
        'destination',
        'trip_start',
        'trip_end',
        'plan_code',
        'sum_insured',
        'travel_days',
        'age_at_travel',
        'base_daily_rate',
        'age_multiplier',
        'destination_multiplier',
        'coverage_multiplier',
        'calculated_premium',
        'status',
        'provider_reference',
        'policy_number',
        'last_response',
        'attempt_count',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'trip_start' => 'date',
        'trip_end' => 'date',
        'travel_days' => 'integer',
        'age_at_travel' => 'integer',
        'base_daily_rate' => 'decimal:2',
        'age_multiplier' => 'decimal:2',
        'destination_multiplier' => 'decimal:2',
        'coverage_multiplier' => 'decimal:2',
        'calculated_premium' => 'decimal:2',
        'sum_insured' => 'decimal:2',
        'last_response' => 'array',
        'attempt_count' => 'integer',
    ];

    public function issuanceAttempts(): HasMany
    {
        return $this->hasMany(IssuanceAttempt::class);
    }

    public function providerEvents(): HasMany
    {
        return $this->hasMany(ProviderEvent::class);
    }

    public function stateHistory(): HasMany
    {
        return $this->hasMany(PolicyStateHistory::class)->orderBy('created_at');
    }

    public function latestIssuanceAttempt(): HasMany
    {
        return $this->hasMany(IssuanceAttempt::class)->latest('attempt_number');
    }

    // State transition validation
    public static function validTransitions(): array
    {
        return [
            'RECEIVED' => ['VALIDATED', 'FAILED'],
            'VALIDATED' => ['SUBMITTED', 'FAILED'],
            'SUBMITTED' => ['PENDING_CONFIRMATION', 'FAILED'],
            'PENDING_CONFIRMATION' => ['ISSUED', 'FAILED', 'NEEDS_RECONCILIATION'],
            'NEEDS_RECONCILIATION' => ['ISSUED', 'FAILED', 'PENDING_CONFIRMATION'],
            'ISSUED' => [], // Terminal state
            'FAILED' => ['SUBMITTED'], // Can retry from FAILED
        ];
    }

    public function canTransitionTo(string $newState): bool
    {
        $validTransitions = self::validTransitions();
        $currentState = $this->status;

        return in_array($newState, $validTransitions[$currentState] ?? []);
    }

    public function transitionTo(string $newState, array $metadata = []): bool
    {
        if (!$this->canTransitionTo($newState)) {
            throw new \InvalidArgumentException("Invalid state transition from {$this->status} to {$newState}");
        }

        $oldState = $this->status;
        $this->status = $newState;
        $this->save();

        // Record state history
        $this->stateHistory()->create([
            'from_state' => $oldState,
            'to_state' => $newState,
            'metadata' => $metadata,
        ]);

        return true;
    }

    public function isTerminalState(): bool
    {
        return in_array($this->status, ['ISSUED', 'FAILED']);
    }

    public function generatePolicyNumber(): string
    {
        $prefix = 'POL';
        $date = now()->format('Ymd');
        $random = strtoupper(substr(md5(uniqid('', true)), 0, 6));
        return "{$prefix}-{$date}-{$random}";
    }
}