<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Real support system between production users and the support team.
 * Kept separate from the AI chat tables (chat_threads / chat_messages),
 * which model assistant conversations, not human support.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_threads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject');
            $table->string('category')->default('general');
            $table->string('status')->default('open');       // open | pending | resolved | closed
            $table->string('priority')->default('normal');    // low | normal | high | urgent
            $table->timestamp('last_message_at')->nullable();
            $table->unsignedInteger('unread_for_admin')->default(0);
            $table->unsignedInteger('unread_for_user')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'last_message_at']);
            $table->index(['user_id', 'status']);
            $table->index('assigned_to');
        });

        Schema::create('support_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('thread_id')->constrained('support_threads')->cascadeOnDelete();
            $table->foreignUuid('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('author_type')->default('user');   // user | agent | system
            $table->text('body');
            $table->json('attachments')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['thread_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_threads');
    }
};
