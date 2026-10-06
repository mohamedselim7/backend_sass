<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Execution tracking for AI work. Results stay in content_generations;
 * this table only records the lifecycle of each logical operation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_generation_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('brand_id')->nullable();
            $table->uuid('content_generation_id')->nullable();
            $table->string('feature', 64);
            $table->string('status', 20)->default('queued');
            $table->string('provider', 64)->nullable();
            $table->string('model', 128)->nullable();
            $table->string('queue_name', 64)->nullable();
            $table->string('idempotency_key', 191)->nullable();
            $table->char('request_hash', 40)->nullable();
            $table->uuid('credit_log_id')->nullable();
            $table->unsignedInteger('credits_reserved')->nullable();
            $table->json('payload')->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->unsignedInteger('total_tokens')->nullable();
            $table->decimal('estimated_cost', 12, 6)->nullable();
            $table->decimal('actual_cost', 12, 6)->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('dispatch_attempts')->default(0);
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'idempotency_key']);
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'dispatched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_generation_jobs');
    }
};
