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
        // Normalize counseling_sessions table
        Schema::table('counseling_sessions', function (Blueprint $table) {
            // Add foreign keys to normalized tables
            $table->foreignId('session_type_id')->nullable()->after('session_type')->constrained('session_types')->onDelete('cascade');
            $table->foreignId('session_status_id')->nullable()->after('status')->constrained('session_statuses')->onDelete('cascade');
            
            // Add indexes for better performance
            $table->index(['student_id', 'session_status_id']);
            $table->index(['counselor_id', 'session_status_id']);
            $table->index('started_at');
            $table->index('ended_at');
        });

        // Normalize user_roles table
        Schema::table('user_roles', function (Blueprint $table) {
            // Add foreign key to roles table
            $table->foreignId('role_id')->nullable()->after('role')->constrained('roles')->onDelete('cascade');
            
            // Add index for better performance
            $table->index(['user_id', 'role_id']);
        });

        // Normalize notifications table
        Schema::table('notifications', function (Blueprint $table) {
            // Add foreign key to notification_types table
            $table->foreignId('notification_type_id')->nullable()->after('id')->constrained('notification_types')->onDelete('cascade');
            
            // Add indexes for better performance
            $table->index(['user_id', 'notification_type_id']);
            $table->index(['read', 'created_at']);
        });

        // Normalize profiles table - remove redundant email
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn('email');
            
            // Add indexes
            $table->index('user_id');
        });

        // Add indexes to messages table
        Schema::table('messages', function (Blueprint $table) {
            $table->index(['session_id', 'sender_id']);
            $table->index('created_at');
        });

        // Add indexes to appointments table
        Schema::table('appointments', function (Blueprint $table) {
            $table->index(['student_id', 'counselor_id']);
            $table->index(['scheduled_at', 'status']);
        });

        // Add indexes to ai_diagnostics table
        Schema::table('ai_diagnostics', function (Blueprint $table) {
            $table->index(['student_id', 'created_at']);
            $table->index('risk_level');
        });

        // Add indexes to counselor_wellness_logs table
        Schema::table('counselor_wellness_logs', function (Blueprint $table) {
            $table->index(['counselor_id', 'created_at']);
        });

        // Add indexes to panic_logs table
        Schema::table('panic_logs', function (Blueprint $table) {
            $table->index(['student_id', 'created_at']);
            $table->index('location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse counseling_sessions table changes
        Schema::table('counseling_sessions', function (Blueprint $table) {
            $table->dropForeign(['session_type_id']);
            $table->dropForeign(['session_status_id']);
            $table->dropColumn(['session_type_id', 'session_status_id']);
            $table->dropIndex(['student_id', 'session_status_id']);
            $table->dropIndex(['counselor_id', 'session_status_id']);
            $table->dropIndex(['started_at']);
            $table->dropIndex(['ended_at']);
        });

        // Reverse user_roles table changes
        Schema::table('user_roles', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn('role_id');
            $table->dropIndex(['user_id', 'role_id']);
        });

        // Reverse notifications table changes
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign(['notification_type_id']);
            $table->dropColumn('notification_type_id');
            $table->dropIndex(['user_id', 'notification_type_id']);
            $table->dropIndex(['read', 'created_at']);
        });

        // Reverse profiles table changes
        Schema::table('profiles', function (Blueprint $table) {
            $table->string('email')->nullable();
            $table->dropIndex('user_id');
        });

        // Drop indexes from other tables
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['session_id', 'sender_id']);
            $table->dropIndex('created_at');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex(['student_id', 'counselor_id']);
            $table->dropIndex(['scheduled_at', 'status']);
        });

        Schema::table('ai_diagnostics', function (Blueprint $table) {
            $table->dropIndex(['student_id', 'created_at']);
            $table->dropIndex('risk_level');
        });

        Schema::table('counselor_wellness_logs', function (Blueprint $table) {
            $table->dropIndex(['counselor_id', 'created_at']);
        });

        Schema::table('panic_logs', function (Blueprint $table) {
            $table->dropIndex(['student_id', 'created_at']);
            $table->dropIndex('location');
        });
    }
};
