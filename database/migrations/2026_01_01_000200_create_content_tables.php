<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_generations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('brand_name')->nullable();
            $table->string('business_type')->nullable();
            $table->longText('business_brief')->nullable();
            $table->longText('monthly_brief')->nullable();
            $table->json('options')->nullable();
            $table->string('status', 32)->default('pending');
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->string('write_mode', 32)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'created_at']);
            $table->index(['brand_id', 'created_at']);
            $table->index('status');
        });

        Schema::create('content_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('generation_id')->constrained('content_generations')->cascadeOnDelete();
            $table->foreignUuid('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('plan_index')->default(0);
            $table->string('name');
            $table->text('strategy')->nullable();
            $table->text('goal')->nullable();
            $table->text('target_audience')->nullable();
            $table->json('pillars')->nullable();
            $table->json('funnel')->nullable();
            $table->json('formats')->nullable();
            $table->string('language_id')->nullable();
            $table->string('dialect_id')->nullable();
            $table->string('tone_id')->nullable();
            $table->timestamps();

            $table->index(['generation_id', 'plan_index']);
        });

        Schema::create('content_posts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('plan_id')->nullable()->constrained('content_plans')->cascadeOnDelete();
            $table->foreignUuid('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('post_number')->default(0);
            $table->string('content_type')->nullable();
            $table->string('funnel_stage')->nullable();
            $table->text('headline')->nullable();
            $table->longText('content')->nullable();
            $table->text('cta')->nullable();
            $table->text('design_idea')->nullable();
            $table->string('platform')->nullable();
            $table->string('status', 32)->default('generated');
            $table->string('reject_reason')->nullable();
            $table->string('source', 32)->nullable();
            $table->json('hashtags')->nullable();
            $table->json('media')->nullable();
            $table->text('notes')->nullable();
            $table->json('suggested_platforms')->nullable();
            $table->string('language_id')->nullable();
            $table->string('dialect_id')->nullable();
            $table->string('tone_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['plan_id', 'post_number']);
            $table->index(['brand_id', 'status']);
            $table->index(['user_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_posts');
        Schema::dropIfExists('content_plans');
        Schema::dropIfExists('content_generations');
    }
};
