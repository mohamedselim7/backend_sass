<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive only: columns the "marketing angles library" needs, plus a usage
 * log. Existing angle rows keep every value they had.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_angles', function (Blueprint $table) {
            if (! Schema::hasColumn('marketing_angles', 'expertise_id')) {
                $table->string('expertise_id', 120)->nullable()->after('brand_id');
                $table->index(['user_id', 'expertise_id']);
            }
            if (! Schema::hasColumn('marketing_angles', 'details')) {
                $table->json('details')->nullable()->after('understanding');
            }
            if (! Schema::hasColumn('marketing_angles', 'source')) {
                $table->string('source', 16)->default('ai')->after('details');
            }
            if (! Schema::hasColumn('marketing_angles', 'edited_by_user')) {
                $table->boolean('edited_by_user')->default(false)->after('source');
            }
            if (! Schema::hasColumn('marketing_angles', 'usage_count')) {
                $table->unsignedInteger('usage_count')->default(0)->after('edited_by_user');
            }
            if (! Schema::hasColumn('marketing_angles', 'last_used_at')) {
                $table->timestamp('last_used_at')->nullable()->after('usage_count');
            }
        });

        if (! Schema::hasTable('marketing_angle_usages')) {
            Schema::create('marketing_angle_usages', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('marketing_angle_id')->constrained()->cascadeOnDelete();
                $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
                $table->string('generation_id', 120)->nullable();
                $table->text('brief_excerpt')->nullable();
                $table->json('angle_snapshot')->nullable();
                $table->timestamps();

                $table->index(['marketing_angle_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        // Non-destructive by design.
    }
};
