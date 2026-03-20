<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->enum('role', ['student', 'staff', 'counselor', 'admin']);
            $table->boolean('approved')->default(true);
            $table->boolean('is_active')->default(true);
            $table->string('full_name')->nullable();
            $table->string('id_number')->nullable();
            $table->timestamps();

            $table->index(['role', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_accounts');
    }
};

