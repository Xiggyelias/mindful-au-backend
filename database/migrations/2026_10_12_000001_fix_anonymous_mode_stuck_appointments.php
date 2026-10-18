<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data-repair migration: clears is_anonymous on upcoming appointments where the student's
 * profile has anonymous_mode = false.
 *
 * This fixes the "stuck on anonymous mode" bug where ProfileController previously only
 * synced appointments within the currently-open call window. Any future appointment that
 * was booked while anonymous_mode was on, but then the student turned it off, would
 * remain stuck showing "Anonymous User" on the counselor's dashboard indefinitely.
 *
 * This migration does a one-time bulk repair so existing data reflects the student's
 * current profile choice. Future toggles are handled by the fixed ProfileController which
 * now syncs ALL upcoming appointments regardless of call window status.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('appointments') || ! Schema::hasTable('profiles')) {
            return;
        }

        // Find all upcoming/active appointments that are flagged anonymous, but whose
        // student's profile says anonymous_mode = false (i.e. they turned it off).
        // Only touch scheduled/confirmed/pending appointments — completed/cancelled ones
        // are historical records and should not be rewritten.
        $hasCallType = Schema::hasColumn('appointments', 'call_type');

        $rows = DB::table('appointments')
            ->join('profiles', 'appointments.student_id', '=', 'profiles.user_id')
            ->where('appointments.is_anonymous', true)
            ->where('profiles.anonymous_mode', false)
            ->whereIn('appointments.status', ['scheduled', 'confirmed', 'pending'])
            ->where('appointments.scheduled_at', '>=', now()->subHours(3))
            ->select('appointments.id', 'appointments.notes')
            ->get();

        foreach ($rows as $row) {
            $updates = [
                'is_anonymous' => false,
                'anonymous_id' => null,
            ];

            // Restore video format for appointments that were forced to audio by anonymity.
            // "Online audio" was set by the anonymous booking path; revert to "Online" (video).
            // Physical sessions ("physical …" prefix) keep their existing notes unchanged.
            $notes = strtolower(trim((string) ($row->notes ?? '')));
            if (str_starts_with($notes, 'online audio')) {
                $updates['notes'] = 'Online';
                if ($hasCallType) {
                    $updates['call_type'] = 'video';
                }
            }

            DB::table('appointments')
                ->where('id', $row->id)
                ->update($updates);
        }
    }

    public function down(): void
    {
        // This migration repairs data — there is no safe rollback because we cannot know
        // which appointments were intentionally booked as anonymous vs. stuck by the bug.
        // Rolling back would require restoring backed-up data.
    }
};
