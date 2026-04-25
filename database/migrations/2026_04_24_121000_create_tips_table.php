<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('tips')) {
            return;
        }

        Schema::create('tips', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->text('content');
            $table->string('category', 60);
            $table->string('audience', 32)->default('all');
            $table->json('mood_tags')->nullable();
            $table->unsignedTinyInteger('priority')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['audience', 'is_active', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tips');
    }
};
