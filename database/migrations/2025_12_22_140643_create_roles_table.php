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
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // e.g., 'admin', 'counselor', 'student'
            $table->string('display_name'); // e.g., 'Administrator', 'Counselor', 'Student'
            $table->text('description')->nullable();
            $table->string('color')->nullable(); // Color code for UI
            $table->string('icon')->nullable(); // Icon name or emoji
            $table->boolean('is_active')->default(true);
            $table->boolean('requires_approval')->default(false); // Whether role needs admin approval
            $table->integer('level')->default(0); // Permission level (higher = more permissions)
            $table->json('permissions')->nullable(); // Specific permissions
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
