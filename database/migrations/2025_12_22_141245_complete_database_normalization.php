<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Normalize counseling_sessions table - check if columns exist before adding
        if (!Schema::hasColumn('counseling_sessions', 'session_type_id')) {
            Schema::table('counseling_sessions', function (Blueprint $table) {
                $table->foreignId('session_type_id')->nullable()->after('session_type')->constrained('session_types')->onDelete('cascade');
            });
        }

        if (!Schema::hasColumn('counseling_sessions', 'session_status_id')) {
            Schema::table('counseling_sessions', function (Blueprint $table) {
                $table->foreignId('session_status_id')->nullable()->after('status')->constrained('session_statuses')->onDelete('cascade');
            });
        }

        // Normalize user_roles table
        if (!Schema::hasColumn('user_roles', 'role_id')) {
            Schema::table('user_roles', function (Blueprint $table) {
                $table->foreignId('role_id')->nullable()->after('role')->constrained('roles')->onDelete('cascade');
            });
        }

        // Normalize notifications table
        if (!Schema::hasColumn('notifications', 'notification_type_id')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->foreignId('notification_type_id')->nullable()->after('id')->constrained('notification_types')->onDelete('cascade');
            });
        }

        // Remove redundant email from profiles if it exists
        if (Schema::hasColumn('profiles', 'email')) {
            Schema::table('profiles', function (Blueprint $table) {
                $table->dropColumn('email');
            });
        }

        // Add indexes for better performance (check if they don't exist)
        $this->addIndexIfNotExists('counseling_sessions', ['student_id', 'session_status_id']);
        $this->addIndexIfNotExists('counseling_sessions', ['counselor_id', 'session_status_id']);
        $this->addIndexIfNotExists('counseling_sessions', 'started_at');
        $this->addIndexIfNotExists('counseling_sessions', 'ended_at');
        $this->addIndexIfNotExists('user_roles', ['user_id', 'role_id']);
        $this->addIndexIfNotExists('notifications', ['user_id', 'notification_type_id']);
        $this->addIndexIfNotExists('notifications', ['read', 'created_at']);
        $this->addIndexIfNotExists('messages', ['session_id', 'sender_id']);
        $this->addIndexIfNotExists('messages', 'created_at');
        $this->addIndexIfNotExists('appointments', ['student_id', 'counselor_id']);
        $this->addIndexIfNotExists('appointments', ['scheduled_at', 'status']);
        $this->addIndexIfNotExists('ai_diagnostics', ['student_id', 'created_at']);
        $this->addIndexIfNotExists('ai_diagnostics', 'risk_level');
        $this->addIndexIfNotExists('counselor_wellness_logs', ['counselor_id', 'created_at']);
        $this->addIndexIfNotExists('panic_logs', ['student_id', 'created_at']);
        $this->addIndexIfNotExists('panic_logs', 'location');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This is a completion migration, so we won't reverse to avoid conflicts
        // The original normalization migration handles the rollback
    }

    /**
     * Helper method to add index if it doesn't exist
     */
    private function addIndexIfNotExists(string $table, $columns): void
    {
        $indexName = is_array($columns) ? $table . '_' . implode('_', $columns) . '_index' : $table . '_' . $columns . '_index';
        
        if (!Schema::hasIndex($table, $indexName)) {
            Schema::table($table, function (Blueprint $table) use ($columns) {
                $table->index($columns);
            });
        }
    }
};
