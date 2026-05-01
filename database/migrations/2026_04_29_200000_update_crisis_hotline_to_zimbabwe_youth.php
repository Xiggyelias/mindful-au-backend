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
        // Update or create the crisis hotline with Zimbabwe Youth Helpline
        SystemSetting::query()->updateOrCreate(
            ['key' => 'crisis_hotline'],
            ['value' => '393 (Youth Helpline)']
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Optionally revert to empty or previous value
        SystemSetting::query()
            ->where('key', 'crisis_hotline')
            ->update(['value' => '']);
    }
};
