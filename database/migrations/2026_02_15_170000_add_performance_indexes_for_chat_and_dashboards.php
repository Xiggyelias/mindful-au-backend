<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->createIndexSafe(
            'counseling_sessions',
            'idx_sessions_counselor_chat_open',
            '(counselor_id, session_type, status, updated_at, id)'
        );
        $this->createIndexSafe(
            'counseling_sessions',
            'idx_sessions_peer_chat_open',
            '(peer_counselor_id, assigned_role, session_type, status, updated_at, id)'
        );
        $this->createIndexSafe(
            'counseling_sessions',
            'idx_sessions_student_chat_open',
            '(student_id, session_type, status, updated_at, id)'
        );
        $this->createIndexSafe(
            'appointments',
            'idx_appointments_counselor_scheduled_status',
            '(counselor_id, scheduled_at, status)'
        );
        $this->createIndexSafe(
            'ai_diagnostics',
            'idx_ai_diagnostics_created_risk_student',
            '(created_at, risk_level, student_id)'
        );
    }

    public function down(): void
    {
        $this->dropIndexSafe('counseling_sessions', 'idx_sessions_counselor_chat_open');
        $this->dropIndexSafe('counseling_sessions', 'idx_sessions_peer_chat_open');
        $this->dropIndexSafe('counseling_sessions', 'idx_sessions_student_chat_open');
        $this->dropIndexSafe('appointments', 'idx_appointments_counselor_scheduled_status');
        $this->dropIndexSafe('ai_diagnostics', 'idx_ai_diagnostics_created_risk_student');
    }

    private function createIndexSafe(string $table, string $indexName, string $columnsSql): void
    {
        try {
            DB::statement("CREATE INDEX {$indexName} ON {$table} {$columnsSql}");
        } catch (\Throwable) {
            // Ignore if the index already exists or the driver does not support this DDL shape.
        }
    }

    private function dropIndexSafe(string $table, string $indexName): void
    {
        try {
            DB::statement("DROP INDEX {$indexName} ON {$table}");
        } catch (\Throwable) {
            // Ignore if missing.
        }
    }
};
