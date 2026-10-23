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
        Schema::create('provider_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider_event_id')->unique();
            $table->enum('event_type', ['ISSUED', 'REJECTED', 'TIMEOUT']);
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('policy_request_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_events');
    }
};