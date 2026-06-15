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
        Schema::table('chat_conversations', function (Blueprint $table) {
            // Add foreign key to ai_models table
            $table->foreignId('ai_model_id')->nullable()->after('user_id')->constrained('ai_models')->onDelete('set null');

            // Add indexes for better performance
            $table->index(['user_id', 'is_active']);
            $table->index(['ai_model_id']);
            $table->index('last_message_at');
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            // Remove the redundant metadata JSON column
            $table->dropColumn('metadata');

            // Add indexes for better performance
            $table->index(['conversation_id', 'role']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->dropForeign(['ai_model_id']);
            $table->dropIndex(['user_id', 'is_active']);
            $table->dropIndex(['ai_model_id']);
            $table->dropIndex('last_message_at');
            $table->dropColumn('ai_model_id');
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->json('metadata')->nullable();
            $table->dropIndex(['conversation_id', 'role']);
            $table->dropIndex('created_at');
        });
    }
};
