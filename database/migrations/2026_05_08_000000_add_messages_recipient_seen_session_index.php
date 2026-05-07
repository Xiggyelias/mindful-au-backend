<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Speeds up counselor/student chat list unread aggregation:
 * SELECT session_id, COUNT(*) FROM messages
 * WHERE recipient_id = ? AND seen_at IS NULL GROUP BY session_id
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('messages')) {
            return;
        }

        try {
            DB::statement(
                'CREATE INDEX messages_recipient_seen_session_idx ON messages (recipient_id, seen_at, session_id)'
            );
        } catch (\Throwable) {
            // Index may already exist or DDL differs by driver.
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            try {
                DB::statement('DROP INDEX IF EXISTS messages_recipient_seen_session_idx');
            } catch (\Throwable) {
                // Ignore.
            }

            return;
        }

        try {
            DB::statement('DROP INDEX messages_recipient_seen_session_idx ON messages');
        } catch (\Throwable) {
            try {
                Schema::table('messages', function ($table) {
                    $table->dropIndex('messages_recipient_seen_session_idx');
                });
            } catch (\Throwable) {
                // Ignore.
            }
        }
    }
};
