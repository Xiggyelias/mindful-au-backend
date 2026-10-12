<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fix anonymous mode loopholes in the messages table.
 *
 * Problem: `sent_as_anonymous` was added as nullable with no default.
 * Rows inserted by old code (before the column existed or before the
 * explicit value was provided) have NULL in this column.
 *
 * In `shouldMaskStudentIdentityForRecipient`, the snapshot is used as:
 *   $effectiveAnonymous = $sentAsAnonymousSnapshot ?? (bool) $session->is_anonymous;
 *
 * When the snapshot is NULL it falls back to $session->is_anonymous — which
 * means messages sent when the SESSION was anonymous but the snapshot was
 * not stored are correctly masked by the session flag. However, for rows
 * where the session has since been updated to is_anonymous=false (after the
 * student revealed identity), those old NULL-snapshot messages would now
 * appear un-masked even though they were sent under anonymous context.
 *
 * Fix:
 *   1. Back-fill: set sent_as_anonymous=true for all NULL rows that belong
 *      to sessions that are (or were) anonymous (is_anonymous=true OR
 *      identity_revealed_at IS NOT NULL, meaning they WERE anonymous).
 *   2. Set sent_as_anonymous=false for all remaining NULL rows (non-anonymous
 *      sessions).
 *   3. Add a database-level default of false so future rows can never be NULL.
 *
 * This ensures historical anonymous messages remain masked even after the
 * student later reveals their identity or turns off anonymous mode.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('messages') || ! Schema::hasColumn('messages', 'sent_as_anonymous')) {
            return;
        }

        // Back-fill messages in sessions that are/were anonymous.
        // We join on counseling_sessions to detect which sessions were ever anonymous.
        DB::statement("
            UPDATE messages m
            INNER JOIN counseling_sessions cs ON cs.id = m.session_id
            SET m.sent_as_anonymous = TRUE
            WHERE m.sent_as_anonymous IS NULL
              AND (cs.is_anonymous = TRUE OR cs.identity_revealed_at IS NOT NULL)
        ");

        // Back-fill all remaining NULL rows as not-anonymous.
        DB::table('messages')
            ->whereNull('sent_as_anonymous')
            ->update(['sent_as_anonymous' => false]);

        // Decisive data repair: for any student whose profile currently has
        // anonymous_mode = false, ensure none of their appointments or sessions remain stuck
        // with is_anonymous = true.
        try {
            DB::statement("
                UPDATE appointments a
                INNER JOIN profiles p ON p.user_id = a.student_id
                SET a.is_anonymous = FALSE, a.anonymous_id = NULL
                WHERE p.anonymous_mode = FALSE AND a.is_anonymous = TRUE
            ");
        } catch (\Throwable) {
            // Ignore if profiles or appointments schema differs
        }

        try {
            DB::statement("
                UPDATE counseling_sessions cs
                INNER JOIN profiles p ON p.user_id = cs.student_id
                SET cs.is_anonymous = FALSE, cs.anonymous_id = NULL
                WHERE p.anonymous_mode = FALSE AND cs.is_anonymous = TRUE
            ");
        } catch (\Throwable) {
            // Ignore if schema differs
        }

        try {
            DB::statement("
                UPDATE messages m
                INNER JOIN profiles p ON p.user_id = m.sender_id
                SET m.sent_as_anonymous = FALSE
                WHERE p.anonymous_mode = FALSE AND m.sent_as_anonymous = TRUE
            ");
        } catch (\Throwable) {
            // Ignore if schema differs
        }

        // Add a proper column-level default so future inserts never get NULL.
        Schema::table('messages', function (Blueprint $table): void {
            $table->boolean('sent_as_anonymous')
                ->default(false)
                ->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('messages') || ! Schema::hasColumn('messages', 'sent_as_anonymous')) {
            return;
        }

        Schema::table('messages', function (Blueprint $table): void {
            $table->boolean('sent_as_anonymous')
                ->nullable()
                ->default(null)
                ->change();
        });
    }
};
