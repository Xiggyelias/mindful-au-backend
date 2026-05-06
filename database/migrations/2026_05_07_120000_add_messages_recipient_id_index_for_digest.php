<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Speeds up incoming-digest cursor (max id) and unread scans filtered by recipient_id.
 * Composite (session_id, recipient_id, seen_at) already exists from seen-receipts migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('messages')) {
            return;
        }

        try {
            DB::statement('CREATE INDEX messages_recipient_id_id_idx ON messages (recipient_id, id)');
        } catch (\Throwable) {
            // Index may already exist or driver uses different DDL.
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            try {
                DB::statement('DROP INDEX IF EXISTS messages_recipient_id_id_idx');
            } catch (\Throwable) {
                // Ignore.
            }

            return;
        }

        try {
            DB::statement('DROP INDEX messages_recipient_id_id_idx ON messages');
        } catch (\Throwable) {
            try {
                Schema::table('messages', function ($table) {
                    $table->dropIndex('messages_recipient_id_id_idx');
                });
            } catch (\Throwable) {
                // Ignore.
            }
        }
    }
};
