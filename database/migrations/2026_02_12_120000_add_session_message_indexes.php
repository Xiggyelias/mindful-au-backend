<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->index(['session_id', 'id'], 'messages_session_id_id_index');
            $table->index(['session_id', 'created_at'], 'messages_session_id_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_session_id_id_index');
            $table->dropIndex('messages_session_id_created_at_index');
        });
    }
};
