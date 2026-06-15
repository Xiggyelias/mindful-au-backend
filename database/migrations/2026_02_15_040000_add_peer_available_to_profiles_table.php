<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('profiles')) {
            return;
        }

        Schema::table('profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('profiles', 'peer_available')) {
                $table->boolean('peer_available')->default(true)->after('anonymous_mode');
                $table->index('peer_available');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('profiles')) {
            return;
        }

        Schema::table('profiles', function (Blueprint $table) {
            if (Schema::hasColumn('profiles', 'peer_available')) {
                $table->dropIndex(['peer_available']);
                $table->dropColumn('peer_available');
            }
        });
    }
};
