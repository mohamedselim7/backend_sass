<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('designs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUuid('post_id')->nullable()->constrained('content_posts')->nullOnDelete();
            $table->foreignUuid('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->text('headline')->nullable();
            $table->text('description')->nullable();
            $table->text('design_idea')->nullable();
            $table->string('format', 32)->default('post');
            $table->string('status', 32)->default('pending');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'created_at']);
            $table->index(['brand_id', 'status']);
        });

        Schema::create('design_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('design_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->string('image_url', 2048)->nullable();
            $table->string('image_storage_path', 1024)->nullable();
            $table->text('instructions')->nullable();
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->timestamps();

            $table->unique(['design_id', 'version']);
        });

        Schema::create('design_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUuid('post_id')->nullable()->constrained('content_posts')->nullOnDelete();
            $table->foreignUuid('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('brand_name')->nullable();
            $table->text('headline')->nullable();
            $table->longText('content')->nullable();
            $table->text('cta')->nullable();
            $table->text('design_idea')->nullable();
            $table->string('platform')->nullable();
            $table->string('content_type')->nullable();
            $table->string('funnel_stage')->nullable();
            $table->text('notes')->nullable();
            $table->json('note_links')->nullable();
            $table->string('design_url', 2048)->nullable();
            $table->string('status', 32)->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_requests');
        Schema::dropIfExists('design_versions');
        Schema::dropIfExists('designs');
    }
};
