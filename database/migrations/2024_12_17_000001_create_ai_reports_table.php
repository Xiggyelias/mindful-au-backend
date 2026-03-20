<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_reports', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // weekly_heatmap, monthly_trend, risk_assessment, counselor_burnout
            $table->enum('status', ['pending', 'generating', 'ready', 'failed'])->default('ready');
            $table->text('summary')->nullable();
            $table->json('data')->nullable();
            $table->string('file_path')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_reports');
    }
};
