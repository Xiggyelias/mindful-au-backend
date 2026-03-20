<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->createIndexSafe(
            'messages',
            'idx_messages_sender_created_at',
            '(sender_id, created_at)'
        );
        $this->createIndexSafe(
            'messages',
            'idx_messages_session_sender_created_at',
            '(session_id, sender_id, created_at)'
        );
    }

    public function down(): void
    {
        $this->dropIndexSafe('messages', 'idx_messages_sender_created_at');
        $this->dropIndexSafe('messages', 'idx_messages_session_sender_created_at');
    }

    private function createIndexSafe(string $table, string $indexName, string $columnsSql): void
    {
        try {
            DB::statement("CREATE INDEX {$indexName} ON {$table} {$columnsSql}");
        } catch (\Throwable) {
            // Ignore if already exists or unsupported by current database driver.
        }
    }

    private function dropIndexSafe(string $table, string $indexName): void
    {
        try {
            DB::statement("DROP INDEX {$indexName} ON {$table}");
        } catch (\Throwable) {
            // Ignore if the index is missing or this DDL variant is unsupported.
        }
    }
};
