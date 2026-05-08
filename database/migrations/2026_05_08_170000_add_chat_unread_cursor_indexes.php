<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('messages')) {
            return;
        }

        $this->createIndexSafe(
            'messages',
            'messages_recipient_seen_id_idx',
            '(recipient_id, seen_at, id)'
        );

        $this->createIndexSafe(
            'messages',
            'messages_session_recipient_seen_id_idx',
            '(session_id, recipient_id, seen_at, id)'
        );
    }

    public function down(): void
    {
        $this->dropIndexSafe('messages', 'messages_recipient_seen_id_idx');
        $this->dropIndexSafe('messages', 'messages_session_recipient_seen_id_idx');
    }

    private function createIndexSafe(string $table, string $indexName, string $columnsSql): void
    {
        try {
            DB::statement("CREATE INDEX {$indexName} ON {$table} {$columnsSql}");
        } catch (\Throwable) {
            // Ignore if the index already exists or the current driver uses different DDL.
        }
    }

    private function dropIndexSafe(string $table, string $indexName): void
    {
        $driver = Schema::getConnection()->getDriverName();

        try {
            if ($driver === 'mysql') {
                DB::statement("DROP INDEX {$indexName} ON {$table}");
            } else {
                DB::statement("DROP INDEX IF EXISTS {$indexName}");
            }
        } catch (\Throwable) {
            // Ignore if the index is missing or unsupported.
        }
    }
};
