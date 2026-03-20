<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagnostic_questionnaires', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->json('questions');
            $table->enum('status', ['active', 'inactive', 'archived'])->default('active');
            $table->integer('version')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnostic_questionnaires');
    }
};
