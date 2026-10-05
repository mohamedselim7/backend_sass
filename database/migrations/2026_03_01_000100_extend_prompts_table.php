<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prompts', function (Blueprint $table) {
            $table->string('section', 64)->nullable()->after('title');
            $table->longText('prompt')->nullable()->after('section');
            $table->longText('result')->nullable()->after('prompt');
            $table->text('notes')->nullable()->after('result');
            $table->foreignUuid('brand_id')->nullable()->after('notes')->constrained()->nullOnDelete();
            $table->string('brand_name')->nullable()->after('brand_id');
            $table->boolean('is_favorite')->default(false)->after('is_shared');

            $table->index(['user_id', 'is_favorite', 'created_at']);
        });

        // Backfill the new required-in-practice `prompt` column from the legacy `body` column.
        DB::table('prompts')->whereNull('prompt')->update(['prompt' => DB::raw('body')]);
    }

    public function down(): void
    {
        Schema::table('prompts', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'is_favorite', 'created_at']);
            $table->dropConstrainedForeignId('brand_id');
            $table->dropColumn(['section', 'prompt', 'result', 'notes', 'brand_name', 'is_favorite']);
        });
    }
};
