<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tip_favorites')) {
            return;
        }

        Schema::create('tip_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tip_id')->constrained('tips')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'tip_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tip_favorites');
    }
};
