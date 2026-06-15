<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_diagnostics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('session_id')->nullable()->constrained('counseling_sessions')->onDelete('set null');
            $table->integer('stress_level')->nullable(); // 0-100
            $table->integer('anxiety_level')->nullable(); // 0-100
            $table->integer('depression_level')->nullable(); // 0-100
            $table->string('mood')->nullable();
            $table->enum('risk_level', ['low', 'medium', 'high', 'critical'])->nullable();
            $table->text('insights')->nullable();
            $table->text('recommendations')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_diagnostics');
    }
};
