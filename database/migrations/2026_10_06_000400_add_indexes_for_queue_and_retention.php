<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Justified by real query patterns we found:
 * - AdminStatsController::usage()/activity() order by created_at with no
 *   other filter; usage_logs/activity_logs already index created_at via
 *   composite (user_id|feature|provider, created_at) but a bare created_at
 *   index helps the admin "all rows" listing and the new retention pruning
 *   commands (`WHERE created_at < ?`).
 * - ai_generation_jobs is swept by ReconcileAiJobs on queue_name for stuck
 *   jobs per lane; add that alongside the existing (status, dispatched_at).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usage_logs', function (Blueprint $table) {
            $table->index('created_at', 'usage_logs_created_at_index');
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index('created_at', 'activity_logs_created_at_index');
        });

        Schema::table('ai_generation_jobs', function (Blueprint $table) {
            $table->index(['queue_name', 'status'], 'ai_generation_jobs_queue_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('usage_logs', function (Blueprint $table) {
            $table->dropIndex('usage_logs_created_at_index');
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex('activity_logs_created_at_index');
        });

        Schema::table('ai_generation_jobs', function (Blueprint $table) {
            $table->dropIndex('ai_generation_jobs_queue_status_index');
        });
    }
};
