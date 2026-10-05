<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('notification_preferences')->nullable()->after('active_model');
            $table->timestamp('welcome_granted_at')->nullable()->after('notification_preferences');
            $table->timestamp('onboarded_at')->nullable()->after('welcome_granted_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['notification_preferences', 'welcome_granted_at', 'onboarded_at']);
        });
    }
};
