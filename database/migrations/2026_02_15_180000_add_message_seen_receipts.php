<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (! Schema::hasColumn('messages', 'recipient_id')) {
                $table->unsignedBigInteger('recipient_id')->nullable()->after('sender_id');
            }

            if (! Schema::hasColumn('messages', 'seen_at')) {
                $table->timestamp('seen_at')->nullable()->after('is_encrypted');
            }
        });

        try {
            Schema::table('messages', function (Blueprint $table) {
                $table->index(['session_id', 'recipient_id', 'seen_at'], 'messages_session_recipient_seen_idx');
            });
        } catch (Throwable) {
            // Index already exists or unsupported by current driver.
        }

        try {
            Schema::table('messages', function (Blueprint $table) {
                $table->foreign('recipient_id')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        } catch (Throwable) {
            // Foreign key already exists or unsupported by current driver.
        }

        $driver = DB::connection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            try {
                DB::statement("
                    UPDATE messages m
                    INNER JOIN counseling_sessions s ON s.id = m.session_id
                    SET m.recipient_id = CASE
                        WHEN m.sender_id = s.student_id THEN
                            CASE
                                WHEN s.assigned_role = 'peer_counselor' AND s.peer_counselor_id IS NOT NULL
                                    THEN s.peer_counselor_id
                                ELSE s.counselor_id
                            END
                        WHEN s.peer_counselor_id IS NOT NULL AND m.sender_id = s.peer_counselor_id THEN s.student_id
                        WHEN s.counselor_id IS NOT NULL AND m.sender_id = s.counselor_id THEN s.student_id
                        ELSE NULL
                    END
                    WHERE m.recipient_id IS NULL
                ");
            } catch (Throwable) {
                // Best-effort backfill only.
            }
        }
    }

    public function down(): void
    {
        try {
            Schema::table('messages', function (Blueprint $table) {
                $table->dropForeign(['recipient_id']);
            });
        } catch (Throwable) {
            // Ignore if missing.
        }

        try {
            Schema::table('messages', function (Blueprint $table) {
                $table->dropIndex('messages_session_recipient_seen_idx');
            });
        } catch (Throwable) {
            // Ignore if missing.
        }

        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'seen_at')) {
                $table->dropColumn('seen_at');
            }
            if (Schema::hasColumn('messages', 'recipient_id')) {
                $table->dropColumn('recipient_id');
            }
        });
    }
};
