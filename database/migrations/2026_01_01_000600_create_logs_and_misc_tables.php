<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('brand_name')->nullable();
            $table->string('actor')->nullable();
            $table->string('action');
            $table->string('entity')->nullable();
            $table->string('feature')->nullable();
            $table->string('status', 32)->nullable();
            $table->decimal('cost', 12, 4)->nullable();
            $table->json('details')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['feature', 'created_at']);
        });

        Schema::create('usage_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('brand_name')->nullable();
            $table->string('kind')->nullable();
            $table->string('feature')->nullable();
            $table->string('action')->nullable();
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->unsignedBigInteger('tokens')->nullable();
            $table->unsignedBigInteger('prompt_tokens')->nullable();
            $table->unsignedBigInteger('completion_tokens')->nullable();
            $table->unsignedInteger('images')->nullable();
            $table->decimal('cost', 12, 6)->nullable();
            $table->string('ref_id')->nullable();
            $table->string('status', 32)->nullable();
            $table->text('error')->nullable();
            $table->json('details')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['provider', 'created_at']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Null user_id means a broadcast notification for every account.
            $table->foreignUuid('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 64)->default('system');
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('link', 1024)->nullable();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at', 'created_at']);
        });

        // Per-user read/dismiss state for broadcast notifications.
        Schema::create('notification_states', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('notification_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'notification_id']);
        });

        // Generic feature output store (Firestore "workspace_records").
        Schema::create('workspace_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUuid('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('brand_name')->nullable();
            $table->string('feature', 64);
            $table->string('title');
            $table->json('input')->nullable();
            $table->json('result')->nullable();
            $table->string('image_storage_path', 1024)->nullable();
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->string('status', 32)->default('completed');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'feature', 'created_at']);
        });

        Schema::create('marketing_angles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUuid('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->longText('body')->nullable();
            $table->string('category')->nullable();
            $table->json('tags')->nullable();
            $table->json('understanding')->nullable();
            $table->string('status', 32)->default('ready');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('prompts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->longText('body');
            $table->string('category')->nullable();
            $table->json('tags')->nullable();
            $table->boolean('is_shared')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('images', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUuid('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('disk', 32)->default('public');
            $table->string('path', 1024);
            $table->string('url', 2048)->nullable();
            $table->string('mime', 128)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('source', 32)->default('upload');
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('chat_threads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title')->nullable();
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'last_message_at']);
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('thread_id')->constrained('chat_threads')->cascadeOnDelete();
            $table->string('role', 16);
            $table->longText('content')->nullable();
            $table->json('parts')->nullable();
            $table->unsignedBigInteger('tokens')->nullable();
            $table->timestamps();

            $table->index(['thread_id', 'created_at']);
        });

        // Key/value application settings (Firestore "app_config"), e.g. pricing.
        Schema::create('app_configs', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        // Provider API keys owned by system admins — value is encrypted by the model cast.
        Schema::create('provider_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider')->unique();
            $table->text('api_key');
            $table->string('default_model')->nullable();
            $table->boolean('is_active')->default(false);
            $table->string('status', 32)->default('disconnected');
            $table->text('last_error')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'provider_keys', 'app_configs', 'chat_messages', 'chat_threads', 'images', 'prompts',
            'marketing_angles', 'workspace_records', 'notification_states', 'notifications',
            'usage_logs', 'activity_logs',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
