<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->nullable()->constrained('counseling_sessions')->nullOnDelete();
            $table->foreignId('intake_submission_id')->nullable()->constrained('intake_submissions')->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('referred_by')->constrained('users')->cascadeOnDelete();
            $table->string('direction', 20);
            $table->string('target_service', 120);
            $table->text('destination_details')->nullable();
            $table->boolean('consent_granted')->default(false);
            $table->json('shared_fields')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('referred_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('outcome_notes')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status', 'created_at']);
            $table->index(['direction', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
