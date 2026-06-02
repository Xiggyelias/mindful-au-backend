<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('counselor_schedules')) {
            return;
        }

        DB::table('counselor_schedules')
            ->where('end_time', '>', '16:00:00')
            ->update(['end_time' => '16:00:00']);
    }

    public function down(): void
    {
        //
    }
};
