<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfNotExists('counseling_sessions', ['student_id', 'status', 'updated_at']);
        $this->addIndexIfNotExists('counseling_sessions', ['counselor_id', 'status', 'updated_at']);
        $this->addIndexIfNotExists('counseling_sessions', ['peer_counselor_id', 'status', 'updated_at']);
        $this->addIndexIfNotExists('counseling_sessions', ['session_type', 'status', 'updated_at']);

        $this->addIndexIfNotExists('messages', ['session_id', 'id']);
        $this->addIndexIfNotExists('messages', ['recipient_id', 'seen_at']);

        $this->addIndexIfNotExists('notifications', ['user_id', 'read', 'created_at']);
    }

    public function down(): void
    {
        $this->dropIndexIfExists('counseling_sessions', ['student_id', 'status', 'updated_at']);
        $this->dropIndexIfExists('counseling_sessions', ['counselor_id', 'status', 'updated_at']);
        $this->dropIndexIfExists('counseling_sessions', ['peer_counselor_id', 'status', 'updated_at']);
        $this->dropIndexIfExists('counseling_sessions', ['session_type', 'status', 'updated_at']);

        $this->dropIndexIfExists('messages', ['session_id', 'id']);
        $this->dropIndexIfExists('messages', ['recipient_id', 'seen_at']);

        $this->dropIndexIfExists('notifications', ['user_id', 'read', 'created_at']);
    }

    private function addIndexIfNotExists(string $table, array|string $columns): void
    {
        $indexName = is_array($columns)
            ? $table . '_' . implode('_', $columns) . '_index'
            : $table . '_' . $columns . '_index';

        if (!Schema::hasIndex($table, $indexName)) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns): void {
                $blueprint->index($columns);
            });
        }
    }

    private function dropIndexIfExists(string $table, array|string $columns): void
    {
        $indexName = is_array($columns)
            ? $table . '_' . implode('_', $columns) . '_index'
            : $table . '_' . $columns . '_index';

        if (Schema::hasIndex($table, $indexName)) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns): void {
                $blueprint->dropIndex($columns);
            });
        }
    }
};
