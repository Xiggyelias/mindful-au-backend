<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (!Schema::hasColumn('appointments', 'is_anonymous')) {
                $table->boolean('is_anonymous')->default(false)->after('counselor_id');
            }

            if (!Schema::hasColumn('appointments', 'anonymous_id')) {
                $table->string('anonymous_id', 32)->nullable()->unique()->after('is_anonymous');
            }

            $table->index(['is_anonymous', 'status'], 'appointments_anonymous_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('appointments_anonymous_status_idx');

            if (Schema::hasColumn('appointments', 'anonymous_id')) {
                $table->dropUnique('appointments_anonymous_id_unique');
                $table->dropColumn('anonymous_id');
            }

            if (Schema::hasColumn('appointments', 'is_anonymous')) {
                $table->dropColumn('is_anonymous');
            }
        });
    }
};
