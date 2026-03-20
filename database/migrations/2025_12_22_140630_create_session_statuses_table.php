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
        Schema::create('session_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // e.g., 'pending', 'active', 'completed', 'cancelled'
            $table->string('display_name'); // e.g., 'Pending', 'Active', 'Completed', 'Cancelled'
            $table->text('description')->nullable();
            $table->string('color')->nullable(); // Color code for UI
            $table->string('icon')->nullable(); // Icon name or emoji
            $table->boolean('is_active')->default(true);
            $table->boolean('is_terminal')->default(false); // Indicates if status is final
            $table->integer('order')->default(0); // For ordering in UI
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('session_statuses');
    }
};
