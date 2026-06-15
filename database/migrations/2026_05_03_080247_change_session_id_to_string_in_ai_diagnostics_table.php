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
        // Confirming there is no data to preserve (Count was 0)
        // Recreating the table is the safest way to change column types in SQLite
        // when doctrine/dbal is missing and foreign keys are involved.
        Schema::dropIfExists('ai_diagnostics');
        Schema::create('ai_diagnostics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            // Changed from foreignId to string to support polymorphic-like behavior
            // for both CounselingSession (int) and Physical Appointment (string "apt_ID")
            $table->string('session_id')->nullable();
            $table->integer('stress_level')->nullable();
            $table->integer('anxiety_level')->nullable();
            $table->integer('depression_level')->nullable();
            $table->string('mood')->nullable();
            $table->enum('risk_level', ['low', 'medium', 'high', 'critical'])->nullable();
            $table->text('insights')->nullable();
            $table->text('recommendations')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_diagnostics');
        Schema::create('ai_diagnostics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('session_id')->nullable()->constrained('counseling_sessions')->onDelete('set null');
            $table->integer('stress_level')->nullable();
            $table->integer('anxiety_level')->nullable();
            $table->integer('depression_level')->nullable();
            $table->string('mood')->nullable();
            $table->enum('risk_level', ['low', 'medium', 'high', 'critical'])->nullable();
            $table->text('insights')->nullable();
            $table->text('recommendations')->nullable();
            $table->timestamps();
        });
    }
};
