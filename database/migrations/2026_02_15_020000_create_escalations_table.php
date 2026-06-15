<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escalations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('counseling_sessions')->cascadeOnDelete();
            $table->foreignId('escalated_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('escalated_to')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('escalation_type', ['peer_to_counselor', 'urgent_flag', 'panic'])->default('peer_to_counselor');
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('high');
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['escalated_by', 'created_at']);
            $table->index(['escalation_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escalations');
    }
};
