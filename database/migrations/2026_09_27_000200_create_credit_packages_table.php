<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive only: a new credit_packages table and a nullable link on payments.
 * No existing column, row, payment or subscription is touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('credit_packages')) {
            Schema::create('credit_packages', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name', 120);
                $table->text('description')->nullable();
                $table->decimal('price', 12, 2);
                $table->string('currency', 3)->default('EGP');
                $table->unsignedInteger('credits');
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['is_active', 'sort_order']);
            });
        }

        if (! Schema::hasColumn('payments', 'credit_package_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->foreignUuid('credit_package_id')->nullable()->after('subscription_id')
                    ->constrained('credit_packages')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive: payments may reference packages, so
        // rolling back never drops data. Remove manually if ever required.
    }
};
