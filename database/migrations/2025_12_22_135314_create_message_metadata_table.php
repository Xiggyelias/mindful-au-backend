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
        Schema::create('message_metadata', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('chat_messages')->onDelete('cascade');
            $table->string('key'); // e.g., 'tokens_used', 'processing_time', 'temperature'
            $table->text('value'); // Store the actual value
            $table->string('type')->default('string'); // 'string', 'integer', 'decimal', 'json'
            $table->timestamps();

            $table->unique(['message_id', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_metadata');
    }
};
