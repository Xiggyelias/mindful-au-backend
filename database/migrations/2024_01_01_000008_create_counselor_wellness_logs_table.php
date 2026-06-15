<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('counselor_wellness_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('counselor_id')->constrained('users')->onDelete('cascade');
            $table->integer('mood_score')->nullable(); // 0-100
            $table->integer('stress_level')->nullable(); // 0-100
            $table->integer('burnout_index')->nullable(); // 0-100
            $table->text('recommendations')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('counselor_wellness_logs');
    }
};
