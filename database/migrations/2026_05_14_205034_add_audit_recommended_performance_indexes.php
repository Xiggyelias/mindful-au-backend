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
        Schema::table('messages', function (Blueprint $table) {
            $table->index(['recipient_id', 'seen_at'], 'messages_recipient_seen_idx');
        });

        Schema::table('counseling_sessions', function (Blueprint $table) {
            $table->index(['student_id', 'status'], 'sessions_student_status_idx');
            $table->index(['counselor_id', 'status'], 'sessions_counselor_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_recipient_seen_idx');
        });

        Schema::table('counseling_sessions', function (Blueprint $table) {
            $table->dropIndex('sessions_student_status_idx');
            $table->dropIndex('sessions_counselor_status_idx');
        });
    }
};
