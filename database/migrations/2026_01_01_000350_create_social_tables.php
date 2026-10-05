<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A tenant's own platform app credentials (previously "private_integrations").
        Schema::create('social_apps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('platform', 32);
            $table->string('label')->nullable();
            $table->string('app_id')->nullable();
            // Encrypted at rest via the model cast — never logged or returned by the API.
            $table->text('app_secret')->nullable();
            $table->json('scopes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'platform']);
        });

        Schema::create('social_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('social_app_id')->nullable()->constrained('social_apps')->nullOnDelete();
            $table->string('platform', 32);
            $table->string('external_id');
            $table->string('name')->nullable();
            $table->string('username')->nullable();
            $table->string('avatar_url', 1024)->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->json('meta')->nullable();
            $table->string('status', 32)->default('connected');
            $table->timestamps();

            $table->unique(['user_id', 'platform', 'external_id']);
        });

        // Short-lived CSRF state for OAuth redirects.
        Schema::create('oauth_states', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 32);
            $table->string('state')->unique();
            $table->string('redirect_uri', 1024)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_states');
        Schema::dropIfExists('social_accounts');
        Schema::dropIfExists('social_apps');
    }
};
