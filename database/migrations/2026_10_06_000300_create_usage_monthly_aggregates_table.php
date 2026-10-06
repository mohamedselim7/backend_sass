<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Pre-aggregated monthly usage/cost per user, built by iden:aggregate-monthly-usage. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_monthly_aggregates', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('feature')->nullable();
            $table->string('provider')->nullable();
            $table->unsignedBigInteger('total_tokens')->default(0);
            $table->unsignedInteger('total_images')->default(0);
            $table->decimal('total_cost', 14, 6)->default(0);
            $table->unsignedInteger('operation_count')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'year', 'month', 'feature', 'provider'], 'usage_monthly_unique');
            $table->index(['year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_monthly_aggregates');
    }
};
