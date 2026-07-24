<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (! Schema::hasColumn('appointments', 'is_emergency')) {
                $table->boolean('is_emergency')->default(false)->after('is_anonymous');
            }
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (Schema::hasColumn('appointments', 'is_emergency')) {
                $table->dropColumn('is_emergency');
            }
        });
    }
};
