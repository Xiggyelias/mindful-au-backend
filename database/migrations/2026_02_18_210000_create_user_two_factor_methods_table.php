<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_two_factor_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('method', 32)->default('totp');
            $table->text('secret_encrypted');
            $table->text('recovery_codes_encrypted')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'method']);
            $table->index(['user_id', 'verified_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_two_factor_methods');
    }
};
