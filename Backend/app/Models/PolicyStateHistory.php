<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PolicyStateHistory extends Model
{
    use HasFactory;

    protected $table = 'policy_state_history';

    protected $fillable = [
        'policy_request_id',
        'from_state',
        'to_state',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function policyRequest(): BelongsTo
    {
        return $this->belongsTo(PolicyRequest::class);
    }
}