<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('allowed_pages')->nullable()->after('status');
            $table->json('allowed_brands')->nullable()->after('allowed_pages');
            $table->unsignedInteger('command_limit')->default(0)->after('allowed_brands');
            $table->unsignedInteger('commands_used')->default(0)->after('command_limit');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['allowed_pages', 'allowed_brands', 'command_limit', 'commands_used']);
        });
    }
};
