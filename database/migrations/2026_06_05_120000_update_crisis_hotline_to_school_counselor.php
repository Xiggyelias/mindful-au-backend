<?php

use App\Models\SystemSetting;
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
        SystemSetting::query()->updateOrCreate(
            ['key' => 'crisis_hotline'],
            ['value' => '+263 77 406 8265']
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        SystemSetting::query()
            ->where('key', 'crisis_hotline')
            ->update(['value' => '393 (Youth Helpline)']);
    }
};
