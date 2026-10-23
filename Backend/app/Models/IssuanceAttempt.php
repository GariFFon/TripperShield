<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class IssuanceAttempt extends Model
{
    use HasFactory;

    protected $table = 'issuance_attempts';

    protected $fillable = [
        'policy_request_id',
        'attempt_number',
        'request_payload',
        'response_payload',
        'response_status',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'attempt_number' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function policyRequest(): BelongsTo
    {
        return $this->belongsTo(PolicyRequest::class);
    }
}