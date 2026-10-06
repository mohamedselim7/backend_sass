<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets admins configure, per plan, which queue lane AI jobs for that plan's
 * subscribers land on, without hardcoding plan names/prices anywhere in code.
 * Both columns are nullable: a plan with no explicit queue configuration
 * falls back to the app default lane (see PlanQueueResolver).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            // Explicit queue name override, e.g. "ai-high". Takes precedence
            // over queue_priority when both are set.
            $table->string('ai_queue', 64)->nullable()->after('ai_model');
            // Lower number = higher priority. Mapped to a queue lane by
            // PlanQueueResolver (e.g. 1 => ai-high, 2 => ai-default, 3 => ai-low).
            $table->unsignedTinyInteger('queue_priority')->nullable()->after('ai_queue');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['ai_queue', 'queue_priority']);
        });
    }
};
