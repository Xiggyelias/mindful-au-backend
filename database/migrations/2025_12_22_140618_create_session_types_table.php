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
        Schema::create('session_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // e.g., 'chat', 'video', 'voice'
            $table->string('display_name'); // e.g., 'Chat Session', 'Video Call', 'Voice Call'
            $table->text('description')->nullable();
            $table->string('icon')->nullable(); // Icon name or emoji
            $table->boolean('is_active')->default(true);
            $table->integer('max_duration_minutes')->nullable(); // Maximum allowed duration
            $table->json('capabilities')->nullable(); // e.g., ['text_chat', 'file_sharing', 'screen_sharing']
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('session_types');
    }
};
