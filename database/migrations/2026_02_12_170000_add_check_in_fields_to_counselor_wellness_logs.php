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
        Schema::table('counselor_wellness_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('counselor_wellness_logs', 'check_in_answers')) {
                $table->json('check_in_answers')->nullable()->after('notes');
            }

            if (!Schema::hasColumn('counselor_wellness_logs', 'check_in_version')) {
                $table->string('check_in_version', 40)->nullable()->after('check_in_answers');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('counselor_wellness_logs', function (Blueprint $table) {
            if (Schema::hasColumn('counselor_wellness_logs', 'check_in_version')) {
                $table->dropColumn('check_in_version');
            }

            if (Schema::hasColumn('counselor_wellness_logs', 'check_in_answers')) {
                $table->dropColumn('check_in_answers');
            }
        });
    }
};
