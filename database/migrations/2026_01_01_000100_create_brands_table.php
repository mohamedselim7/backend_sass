<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('industry')->nullable();
            $table->text('description')->nullable();
            $table->text('target_audience')->nullable();
            $table->string('target_market')->nullable();
            $table->string('tone_of_voice')->nullable();
            $table->string('content_language')->nullable();
            $table->string('dialect')->nullable();
            $table->json('platforms')->nullable();
            $table->string('logo_url', 1024)->nullable();
            $table->json('colors')->nullable();
            $table->string('fonts')->nullable();
            $table->string('guidelines_url', 1024)->nullable();
            $table->json('reference_images')->nullable();
            $table->string('website')->nullable();
            $table->json('social')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['created_by', 'created_at']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};
