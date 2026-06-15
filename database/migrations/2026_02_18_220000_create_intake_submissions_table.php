<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intake_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('submitter_type', 20);
            $table->boolean('is_anonymous')->default(false);
            $table->string('anonymous_id', 32)->nullable();
            $table->json('presenting_concerns');
            $table->json('risk_answers')->nullable();
            $table->boolean('consent_acknowledged')->default(false);
            $table->string('risk_level', 20)->default('low');
            $table->unsignedSmallInteger('urgency_score')->default(0);
            $table->string('status', 20)->default('new');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('summary')->nullable();
            $table->timestamps();

            $table->unique('anonymous_id');
            $table->index(['status', 'risk_level']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intake_submissions');
    }
};
