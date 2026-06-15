<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('counselor_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('counselor_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->boolean('is_working_day')->default(true);
            $table->time('start_time')->default('08:00:00');
            $table->time('end_time')->default('16:00:00');
            $table->time('break_start')->nullable()->default('13:00:00');
            $table->time('break_end')->nullable()->default('14:00:00');
            $table->unsignedSmallInteger('slot_duration_minutes')->default(30);
            $table->timestamps();

            $table->unique(['counselor_id', 'day_of_week'], 'counselor_schedules_day_unique');
            $table->index(['counselor_id', 'is_working_day'], 'counselor_schedules_working_idx');
        });

        Schema::create('counselor_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('counselor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('counselor_schedule_id')->nullable()->constrained('counselor_schedules')->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->date('slot_date');
            $table->unsignedTinyInteger('day_of_week');
            $table->timestamp('start_time');
            $table->timestamp('end_time');
            $table->enum('status', ['available', 'booked', 'unavailable'])->default('available');
            $table->timestamps();

            $table->unique(['counselor_id', 'start_time', 'end_time'], 'counselor_slots_unique_time');
            $table->index(['counselor_id', 'slot_date', 'status'], 'counselor_slots_lookup_idx');
            $table->index(['appointment_id', 'status'], 'counselor_slots_appointment_idx');
        });

        Schema::table('appointments', function (Blueprint $table) {
            if (! Schema::hasColumn('appointments', 'counselor_slot_id')) {
                $table->foreignId('counselor_slot_id')
                    ->nullable()
                    ->after('counselor_id')
                    ->constrained('counselor_slots')
                    ->nullOnDelete();
                $table->unique('counselor_slot_id', 'appointments_counselor_slot_unique');
            }
        });

        Schema::create('emergency_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('counselor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at');
            $table->boolean('is_after_hours')->default(true);
            $table->unsignedTinyInteger('priority')->default(1);
            $table->enum('status', ['queued', 'assigned', 'resolved', 'cancelled'])->default('queued');
            $table->string('location')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority', 'requested_at'], 'emergency_requests_queue_idx');
            $table->index(['student_id', 'requested_at'], 'emergency_requests_student_idx');
            $table->index(['counselor_id', 'status'], 'emergency_requests_counselor_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emergency_requests');

        Schema::table('appointments', function (Blueprint $table) {
            if (Schema::hasColumn('appointments', 'counselor_slot_id')) {
                $table->dropUnique('appointments_counselor_slot_unique');
                $table->dropConstrainedForeignId('counselor_slot_id');
            }
        });

        Schema::dropIfExists('counselor_slots');
        Schema::dropIfExists('counselor_schedules');
    }
};
