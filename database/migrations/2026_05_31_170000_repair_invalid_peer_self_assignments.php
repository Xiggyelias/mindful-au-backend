<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $invalidSessionIds = DB::table('counseling_sessions')
            ->whereNotNull('peer_counselor_id')
            ->where(function ($query): void {
                $query->whereColumn('peer_counselor_id', 'student_id')
                    ->orWhereColumn('peer_counselor_id', 'counselor_id');
            })
            ->pluck('id');

        if ($invalidSessionIds->isNotEmpty()) {
            DB::table('counseling_sessions')
                ->whereIn('id', $invalidSessionIds->all())
                ->update([
                    'peer_counselor_id' => null,
                    'assigned_role' => 'counselor',
                    'updated_at' => now(),
                ]);
        }

        $invalidAssignmentIds = DB::table('peer_assignments')
            ->join('counseling_sessions', 'counseling_sessions.id', '=', 'peer_assignments.session_id')
            ->where(function ($query): void {
                $query->whereColumn('peer_assignments.peer_counselor_id', 'counseling_sessions.student_id')
                    ->orWhereColumn('peer_assignments.peer_counselor_id', 'counseling_sessions.counselor_id');
            })
            ->pluck('peer_assignments.id');

        if ($invalidAssignmentIds->isNotEmpty()) {
            DB::table('peer_assignments')
                ->whereIn('id', $invalidAssignmentIds->all())
                ->update([
                    'status' => 'closed',
                    'unassigned_at' => now(),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // This migration repairs invalid production data. It is intentionally
        // not reversible because restoring self-assignments would reintroduce
        // the privacy and routing bug.
    }
};
