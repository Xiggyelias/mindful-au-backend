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
        Schema::create('ai_models', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // e.g., 'nvidia/nemotron-nano-9b-v2:free'
            $table->string('display_name'); // e.g., 'NVIDIA Nemotron Nano 9B'
            $table->string('provider'); // e.g., 'nvidia', 'openai', 'anthropic'
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('max_tokens')->nullable();
            $table->decimal('cost_per_input_token', 10, 8)->nullable();
            $table->decimal('cost_per_output_token', 10, 8)->nullable();
            $table->json('capabilities')->nullable(); // Store features like 'streaming', 'function_calling'
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_models');
    }
};
