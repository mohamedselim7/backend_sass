<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cheap prompt-versioning columns: recorded once per generation/design so we
 * can tell which prompt template produced a given record without storing it
 * on every individual post (storage/write-cost would not be "cheap" there).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_generations', function (Blueprint $table) {
            $table->string('prompt_version', 64)->nullable()->after('write_mode');
        });

        Schema::table('designs', function (Blueprint $table) {
            $table->string('prompt_version', 64)->nullable()->after('format');
            $table->string('format_id', 16)->nullable()->after('prompt_version');
        });
    }

    public function down(): void
    {
        Schema::table('content_generations', function (Blueprint $table) {
            $table->dropColumn('prompt_version');
        });

        Schema::table('designs', function (Blueprint $table) {
            $table->dropColumn(['prompt_version', 'format_id']);
        });
    }
};
