<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chat scalability: rolling-summary columns on chat_threads (for bounded AI
 * context) plus composite indexes that cursor-pagination and the thread list
 * rely on. Additive only — safe to run against a non-empty production DB.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_threads', function (Blueprint $table) {
            if (! Schema::hasColumn('chat_threads', 'summary')) {
                $table->longText('summary')->nullable()->after('mode');
            }
            if (! Schema::hasColumn('chat_threads', 'summary_until_message_id')) {
                $table->uuid('summary_until_message_id')->nullable()->after('summary');
            }
            if (! Schema::hasColumn('chat_threads', 'summary_updated_at')) {
                $table->timestamp('summary_updated_at')->nullable()->after('summary_until_message_id');
            }
        });

        Schema::table('chat_threads', function (Blueprint $table) {
            if (! $this->indexExists('chat_threads', 'chat_threads_user_last_message_idx')) {
                $table->index(['user_id', 'last_message_at'], 'chat_threads_user_last_message_idx');
            }
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            if (! $this->indexExists('chat_messages', 'chat_messages_thread_id_idx')) {
                $table->index(['thread_id', 'id'], 'chat_messages_thread_id_idx');
            }
            if (! $this->indexExists('chat_messages', 'chat_messages_thread_created_idx')) {
                $table->index(['thread_id', 'created_at'], 'chat_messages_thread_created_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('chat_threads', function (Blueprint $table) {
            if ($this->indexExists('chat_threads', 'chat_threads_user_last_message_idx')) {
                $table->dropIndex('chat_threads_user_last_message_idx');
            }
            $table->dropColumn(array_filter([
                Schema::hasColumn('chat_threads', 'summary') ? 'summary' : null,
                Schema::hasColumn('chat_threads', 'summary_until_message_id') ? 'summary_until_message_id' : null,
                Schema::hasColumn('chat_threads', 'summary_updated_at') ? 'summary_updated_at' : null,
            ]));
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            if ($this->indexExists('chat_messages', 'chat_messages_thread_id_idx')) {
                $table->dropIndex('chat_messages_thread_id_idx');
            }
            if ($this->indexExists('chat_messages', 'chat_messages_thread_created_idx')) {
                $table->dropIndex('chat_messages_thread_created_idx');
            }
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        try {
            return Schema::getConnection()->getDoctrineSchemaManager()->listTableIndexes($table) !== [] &&
                array_key_exists($indexName, Schema::getConnection()->getDoctrineSchemaManager()->listTableIndexes($table));
        } catch (\Throwable) {
            // Doctrine DBAL may be unavailable; fall back to a raw information_schema check (MySQL).
            try {
                $row = Schema::getConnection()->selectOne(
                    'select count(*) as c from information_schema.statistics where table_schema = database() and table_name = ? and index_name = ?',
                    [$table, $indexName]
                );

                return (int) ($row->c ?? 0) > 0;
            } catch (\Throwable) {
                return false;
            }
        }
    }
};
