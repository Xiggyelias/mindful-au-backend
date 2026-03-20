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
        Schema::create('notification_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // e.g., 'session_reminder', 'appointment_cancelled', 'message_received'
            $table->string('display_name'); // e.g., 'Session Reminder', 'Appointment Cancelled', 'New Message'
            $table->text('description')->nullable();
            $table->string('color')->nullable(); // Color code for UI
            $table->string('icon')->nullable(); // Icon name or emoji
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false); // Whether it's a system-generated notification
            $table->string('default_template')->nullable(); // Default message template
            $table->json('channels')->nullable(); // e.g., ['in_app', 'email', 'sms', 'push']
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_types');
    }
};
