<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tip_deliveries')) {
            return;
        }

        Schema::create('tip_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tip_id')->constrained('tips')->cascadeOnDelete();
            $table->date('delivered_on');
            $table->string('audience', 32);
            $table->string('mood', 32)->nullable();
            $table->boolean('personalized')->default(false);
            $table->foreignId('notification_id')->nullable()->constrained('notifications')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'delivered_on']);
            $table->index(['tip_id', 'delivered_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tip_deliveries');
    }
};
