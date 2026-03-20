<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagnostics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            $table->json('responses');
            $table->integer('total_score');
            $table->enum('risk_level', ['low', 'medium', 'high', 'critical']);
            $table->json('category_scores');
            $table->json('ai_recommendations');
            $table->text('insights')->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->string('anonymous_id')->nullable()->unique();
            $table->timestamps();
            $table->index('student_id');
            $table->index('risk_level');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnostics');
    }
};
