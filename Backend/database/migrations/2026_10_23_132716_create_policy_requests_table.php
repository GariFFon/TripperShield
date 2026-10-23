<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('policy_requests', function (Blueprint $table) {
            $table->id();
            $table->string('client_request_id');
            $table->string('agent_id');
            $table->string('customer_name');
            $table->date('date_of_birth');
            $table->enum('destination', ['DOMESTIC', 'INTERNATIONAL']);
            $table->date('trip_start');
            $table->date('trip_end');
            $table->enum('plan_code', ['BASIC', 'PLUS', 'PREMIUM']);
            $table->decimal('sum_insured', 15, 2);

            // Computed fields
            $table->unsignedInteger('travel_days');
            $table->unsignedInteger('age_at_travel');
            $table->decimal('base_daily_rate', 10, 2);
            $table->decimal('age_multiplier', 4, 2);
            $table->decimal('destination_multiplier', 4, 2);
            $table->decimal('coverage_multiplier', 4, 2);
            $table->decimal('calculated_premium', 15, 2);

            // State and tracking
            $table->enum('status', [
                'RECEIVED',
                'VALIDATED',
                'SUBMITTED',
                'PENDING_CONFIRMATION',
                'ISSUED',
                'FAILED',
                'NEEDS_RECONCILIATION'
            ])->default('RECEIVED');

            $table->string('provider_reference')->nullable();
            $table->string('policy_number')->nullable()->unique();
            $table->json('last_response')->nullable();
            $table->unsignedInteger('attempt_count')->default(0);

            $table->timestamps();

            // Unique constraint for duplicate prevention
            $table->unique(['agent_id', 'client_request_id'], 'unique_agent_client');
            $table->index('agent_id');
            $table->index('status');
            $table->index('provider_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('policy_requests');
    }
};