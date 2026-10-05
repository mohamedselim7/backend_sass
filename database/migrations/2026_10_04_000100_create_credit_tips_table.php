<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive only: tips for reducing credit usage, written by admins and shown
 * to users. No existing table or row is touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('credit_tips')) {
            return;
        }

        Schema::create('credit_tips', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title', 160);
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        // Non-destructive by design: admin-written content is never dropped automatically.
    }
};
