<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intake_submission_id')->constrained('intake_submissions')->cascadeOnDelete();
            $table->string('severity', 20)->default('high');
            $table->string('status', 20)->default('open');
            $table->timestamp('triggered_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['severity', 'status', 'triggered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_alerts');
    }
};
