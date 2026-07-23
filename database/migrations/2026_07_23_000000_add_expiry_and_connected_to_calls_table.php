<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('caller_role');
            $table->timestamp('connected_at')->nullable()->after('expires_at');

            $table->index(['student_id', 'status', 'expires_at'], 'calls_student_status_expires_idx');
            $table->index(['counselor_id', 'status', 'expires_at'], 'calls_counselor_status_expires_idx');
        });
    }

    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->dropIndex('calls_student_status_expires_idx');
            $table->dropIndex('calls_counselor_status_expires_idx');
            $table->dropColumn(['expires_at', 'connected_at']);
        });
    }
};
