<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_risk_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sync_run_id')->nullable()->constrained('sync_runs')->nullOnDelete();
            $table->string('external_event_id', 120)->nullable();
            $table->string('student_identifier', 120);
            $table->string('registration_number', 120)->nullable();
            $table->string('faculty', 120)->nullable();
            $table->string('year_of_study', 40)->nullable();
            $table->string('enrolment_status', 60)->nullable();
            $table->string('risk_type', 60);
            $table->decimal('risk_score', 5, 2)->nullable();
            $table->string('status', 20)->default('new');
            $table->foreignId('linked_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->json('payload');
            $table->timestamps();

            $table->unique('external_event_id');
            $table->index(['registration_number', 'risk_type']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_risk_events');
    }
};
