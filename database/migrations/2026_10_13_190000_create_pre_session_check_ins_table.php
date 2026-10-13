<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pre_session_check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('counselor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignId('session_id')->nullable()->constrained('counseling_sessions')->nullOnDelete();
            $table->string('mood', 32); // great, calm, okay, down, anxious, crisis
            $table->unsignedTinyInteger('mood_score')->default(3); // 1-5
            $table->unsignedTinyInteger('energy_level')->default(3); // 1-5
            $table->unsignedTinyInteger('stress_level')->default(3); // 1-5
            $table->json('primary_concerns')->nullable(); // array of tags
            $table->string('session_goal', 64)->nullable(); // coping_tools, venting, action_plan, grounding
            $table->text('student_notes')->nullable();
            $table->boolean('is_crisis')->default(false);
            $table->timestamps();

            $table->index(['student_id', 'created_at']);
            $table->index(['counselor_id', 'created_at']);
            $table->index(['appointment_id']);
            $table->index(['session_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pre_session_check_ins');
    }
};
