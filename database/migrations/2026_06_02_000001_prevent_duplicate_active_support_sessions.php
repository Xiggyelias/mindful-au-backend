<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const WELLNESS_MODEL = 'wellness-assistant-v1';

    public function up(): void
    {
        if (Schema::hasTable('counseling_sessions')) {
            $this->closeDuplicateDirectCounselorSessions();
            $this->closeDuplicatePeerSupportSessions();
            $this->createSupportSessionIndexes();
        }

        if (Schema::hasTable('peer_assignments')) {
            $this->closeDuplicatePeerAssignments();
            $this->createPeerAssignmentIndex();
        }

        if (Schema::hasTable('chat_conversations')) {
            $this->closeDuplicateWellnessConversations();
            $this->createWellnessConversationIndex();
        }
    }

    public function down(): void
    {
        $this->dropIndexIfExists('chat_conversations', 'chat_conversations_active_wellness_unique');
        $this->dropIndexIfExists('peer_assignments', 'peer_assignments_active_unique');
        $this->dropIndexIfExists('counseling_sessions', 'sessions_active_peer_unique');
        $this->dropIndexIfExists('counseling_sessions', 'sessions_active_direct_unique');

        if (DB::getDriverName() === 'mysql') {
            $this->dropColumnIfExists('chat_conversations', 'active_wellness_dedupe_key');
            $this->dropColumnIfExists('peer_assignments', 'active_assignment_dedupe_key');
            $this->dropColumnIfExists('counseling_sessions', 'active_peer_dedupe_key');
            $this->dropColumnIfExists('counseling_sessions', 'active_direct_dedupe_key');
        }
    }

    private function closeDuplicateDirectCounselorSessions(): void
    {
        $groups = DB::table('counseling_sessions')
            ->select('student_id', 'counselor_id', 'session_type')
            ->whereNotNull('counselor_id')
            ->whereNull('peer_counselor_id')
            ->whereIn('status', ['pending', 'active'])
            ->where(function ($query): void {
                $query->whereNull('assigned_role')
                    ->orWhere('assigned_role', 'counselor');
            })
            ->groupBy('student_id', 'counselor_id', 'session_type')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($groups as $group) {
            $this->closeDuplicateSessionRows(
                DB::table('counseling_sessions')
                    ->where('student_id', $group->student_id)
                    ->where('counselor_id', $group->counselor_id)
                    ->where('session_type', $group->session_type)
                    ->whereNull('peer_counselor_id')
                    ->whereIn('status', ['pending', 'active'])
                    ->where(function ($query): void {
                        $query->whereNull('assigned_role')
                            ->orWhere('assigned_role', 'counselor');
                    })
            );
        }
    }

    private function closeDuplicatePeerSupportSessions(): void
    {
        $groups = DB::table('counseling_sessions')
            ->select('student_id', 'peer_counselor_id', 'session_type')
            ->whereNotNull('peer_counselor_id')
            ->where('assigned_role', 'peer_counselor')
            ->whereIn('status', ['pending', 'active'])
            ->groupBy('student_id', 'peer_counselor_id', 'session_type')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($groups as $group) {
            $this->closeDuplicateSessionRows(
                DB::table('counseling_sessions')
                    ->where('student_id', $group->student_id)
                    ->where('peer_counselor_id', $group->peer_counselor_id)
                    ->where('session_type', $group->session_type)
                    ->where('assigned_role', 'peer_counselor')
                    ->whereIn('status', ['pending', 'active'])
            );
        }
    }

    private function closeDuplicateSessionRows(\Illuminate\Database\Query\Builder $query): void
    {
        $ids = $query
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();

        $duplicateIds = array_slice($ids, 1);
        if ($duplicateIds === []) {
            return;
        }

        DB::table('counseling_sessions')
            ->whereIn('id', $duplicateIds)
            ->update([
                'status' => 'cancelled',
                'ended_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function closeDuplicatePeerAssignments(): void
    {
        $groups = DB::table('peer_assignments')
            ->select('session_id', 'peer_counselor_id')
            ->where('status', 'active')
            ->groupBy('session_id', 'peer_counselor_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($groups as $group) {
            $ids = DB::table('peer_assignments')
                ->where('session_id', $group->session_id)
                ->where('peer_counselor_id', $group->peer_counselor_id)
                ->where('status', 'active')
                ->orderByDesc('assigned_at')
                ->orderByDesc('id')
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->values()
                ->all();

            $duplicateIds = array_slice($ids, 1);
            if ($duplicateIds === []) {
                continue;
            }

            DB::table('peer_assignments')
                ->whereIn('id', $duplicateIds)
                ->update([
                    'status' => 'reassigned',
                    'unassigned_at' => now(),
                    'updated_at' => now(),
                ]);
        }
    }

    private function closeDuplicateWellnessConversations(): void
    {
        $groups = DB::table('chat_conversations')
            ->select('user_id', 'model')
            ->where('is_active', true)
            ->where('model', self::WELLNESS_MODEL)
            ->groupBy('user_id', 'model')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($groups as $group) {
            $ids = DB::table('chat_conversations')
                ->where('user_id', $group->user_id)
                ->where('model', $group->model)
                ->where('is_active', true)
                ->orderByDesc('last_message_at')
                ->orderByDesc('id')
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->values()
                ->all();

            $duplicateIds = array_slice($ids, 1);
            if ($duplicateIds === []) {
                continue;
            }

            DB::table('chat_conversations')
                ->whereIn('id', $duplicateIds)
                ->update([
                    'is_active' => false,
                    'updated_at' => now(),
                ]);
        }
    }

    private function createSupportSessionIndexes(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            $this->addMysqlGeneratedColumn(
                'counseling_sessions',
                'active_direct_dedupe_key',
                "CASE WHEN `status` IN ('pending','active') AND `counselor_id` IS NOT NULL AND `peer_counselor_id` IS NULL AND (`assigned_role` IS NULL OR `assigned_role` = 'counselor') THEN CONCAT(`student_id`, ':', `counselor_id`, ':', `session_type`) ELSE NULL END"
            );
            $this->addMysqlGeneratedColumn(
                'counseling_sessions',
                'active_peer_dedupe_key',
                "CASE WHEN `status` IN ('pending','active') AND `peer_counselor_id` IS NOT NULL AND `assigned_role` = 'peer_counselor' THEN CONCAT(`student_id`, ':', `peer_counselor_id`, ':', `session_type`) ELSE NULL END"
            );
            $this->createIndexIfMissing(
                'counseling_sessions',
                'sessions_active_direct_unique',
                'CREATE UNIQUE INDEX sessions_active_direct_unique ON counseling_sessions (active_direct_dedupe_key)'
            );
            $this->createIndexIfMissing(
                'counseling_sessions',
                'sessions_active_peer_unique',
                'CREATE UNIQUE INDEX sessions_active_peer_unique ON counseling_sessions (active_peer_dedupe_key)'
            );
            return;
        }

        $this->createIndexIfMissing(
            'counseling_sessions',
            'sessions_active_direct_unique',
            "CREATE UNIQUE INDEX IF NOT EXISTS sessions_active_direct_unique ON counseling_sessions (student_id, counselor_id, session_type) WHERE counselor_id IS NOT NULL AND peer_counselor_id IS NULL AND status IN ('pending','active') AND (assigned_role IS NULL OR assigned_role = 'counselor')"
        );
        $this->createIndexIfMissing(
            'counseling_sessions',
            'sessions_active_peer_unique',
            "CREATE UNIQUE INDEX IF NOT EXISTS sessions_active_peer_unique ON counseling_sessions (student_id, peer_counselor_id, session_type) WHERE peer_counselor_id IS NOT NULL AND assigned_role = 'peer_counselor' AND status IN ('pending','active')"
        );
    }

    private function createPeerAssignmentIndex(): void
    {
        if (DB::getDriverName() === 'mysql') {
            $this->addMysqlGeneratedColumn(
                'peer_assignments',
                'active_assignment_dedupe_key',
                "CASE WHEN `status` = 'active' THEN CONCAT(`session_id`, ':', `peer_counselor_id`) ELSE NULL END"
            );
            $this->createIndexIfMissing(
                'peer_assignments',
                'peer_assignments_active_unique',
                'CREATE UNIQUE INDEX peer_assignments_active_unique ON peer_assignments (active_assignment_dedupe_key)'
            );
            return;
        }

        $this->createIndexIfMissing(
            'peer_assignments',
            'peer_assignments_active_unique',
            "CREATE UNIQUE INDEX IF NOT EXISTS peer_assignments_active_unique ON peer_assignments (session_id, peer_counselor_id) WHERE status = 'active'"
        );
    }

    private function createWellnessConversationIndex(): void
    {
        if (DB::getDriverName() === 'mysql') {
            $this->addMysqlGeneratedColumn(
                'chat_conversations',
                'active_wellness_dedupe_key',
                "CASE WHEN `is_active` = 1 AND `model` = '" . self::WELLNESS_MODEL . "' THEN CONCAT(`user_id`, ':', `model`) ELSE NULL END"
            );
            $this->createIndexIfMissing(
                'chat_conversations',
                'chat_conversations_active_wellness_unique',
                'CREATE UNIQUE INDEX chat_conversations_active_wellness_unique ON chat_conversations (active_wellness_dedupe_key)'
            );
            return;
        }

        $this->createIndexIfMissing(
            'chat_conversations',
            'chat_conversations_active_wellness_unique',
            "CREATE UNIQUE INDEX IF NOT EXISTS chat_conversations_active_wellness_unique ON chat_conversations (user_id, model) WHERE is_active = 1 AND model = '" . self::WELLNESS_MODEL . "'"
        );
    }

    private function addMysqlGeneratedColumn(string $table, string $column, string $expression): void
    {
        if (Schema::hasColumn($table, $column)) {
            return;
        }

        DB::statement(
            "ALTER TABLE `{$table}` ADD COLUMN `{$column}` VARCHAR(191) GENERATED ALWAYS AS ({$expression}) STORED"
        );
    }

    private function createIndexIfMissing(string $table, string $index, string $sql): void
    {
        try {
            if (Schema::hasIndex($table, $index)) {
                return;
            }
        } catch (\Throwable) {
            // Older drivers may not expose index metadata consistently.
        }

        DB::statement($sql);
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        try {
            if (Schema::hasIndex($table, $index) === false) {
                return;
            }
        } catch (\Throwable) {
            // Continue with best-effort SQL below.
        }

        try {
            if (DB::getDriverName() === 'mysql') {
                DB::statement("DROP INDEX `{$index}` ON `{$table}`");
            } else {
                DB::statement("DROP INDEX IF EXISTS {$index}");
            }
        } catch (\Throwable) {
            // Best-effort rollback; duplicate index absence should not block rollback.
        }
    }

    private function dropColumnIfExists(string $table, string $column): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        try {
            DB::statement("ALTER TABLE `{$table}` DROP COLUMN `{$column}`");
        } catch (\Throwable) {
            // Best-effort rollback for generated-column capable databases.
        }
    }
};
