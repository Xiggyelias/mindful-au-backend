<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_files', function (Blueprint $table) {
            if (! Schema::hasColumn('chat_files', 'disk')) {
                // Null = legacy row stored on the disk configured at upload time
                // (chat.attachments.disk). New rows always record their disk so
                // reads keep working when the configured disk changes.
                $table->string('disk', 32)->nullable()->after('file_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('chat_files', function (Blueprint $table) {
            if (Schema::hasColumn('chat_files', 'disk')) {
                $table->dropColumn('disk');
            }
        });
    }
};
