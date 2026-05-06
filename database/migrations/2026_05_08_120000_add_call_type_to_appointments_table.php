<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('call_type', 16)->default('video')->after('notes');
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement(
                "UPDATE appointments SET call_type = 'audio' WHERE LOWER(TRIM(COALESCE(notes, ''))) LIKE 'online audio%'"
            );
            DB::statement(
                "UPDATE appointments SET call_type = 'audio' WHERE is_anonymous = 1 AND LOWER(TRIM(COALESCE(notes, ''))) NOT LIKE 'physical%'"
            );
        } else {
            DB::table('appointments')
                ->whereRaw("LOWER(TRIM(COALESCE(notes, ''))) LIKE ?", ['online audio%'])
                ->update(['call_type' => 'audio']);
            DB::table('appointments')
                ->where('is_anonymous', true)
                ->whereRaw("LOWER(TRIM(COALESCE(notes, ''))) NOT LIKE ?", ['physical%'])
                ->update(['call_type' => 'audio']);
        }
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('call_type');
        });
    }
};
