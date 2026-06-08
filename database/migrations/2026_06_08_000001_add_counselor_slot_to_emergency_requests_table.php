<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emergency_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('emergency_requests', 'counselor_slot_id')) {
                $table->foreignId('counselor_slot_id')
                    ->nullable()
                    ->after('assigned_to')
                    ->constrained('counselor_slots')
                    ->nullOnDelete();

                $table->index(['counselor_slot_id', 'status'], 'emergency_requests_slot_status_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('emergency_requests', function (Blueprint $table) {
            if (Schema::hasColumn('emergency_requests', 'counselor_slot_id')) {
                $table->dropIndex('emergency_requests_slot_status_idx');
                $table->dropConstrainedForeignId('counselor_slot_id');
            }
        });
    }
};
