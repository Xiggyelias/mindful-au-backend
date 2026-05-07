<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->string('caller_role', 16)->default('student')->after('call_type');
            $table->index(['student_id', 'status', 'caller_role', 'created_at'], 'calls_student_status_caller_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->dropIndex('calls_student_status_caller_created_idx');
            $table->dropColumn('caller_role');
        });
    }
};
