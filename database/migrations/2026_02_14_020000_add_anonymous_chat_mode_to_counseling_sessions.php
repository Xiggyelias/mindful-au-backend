<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('counseling_sessions')) {
            return;
        }

        Schema::table('counseling_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('counseling_sessions', 'is_anonymous')) {
                $table->boolean('is_anonymous')->default(false)->after('assigned_role');
            }

            if (! Schema::hasColumn('counseling_sessions', 'anonymous_id')) {
                $table->string('anonymous_id', 32)->nullable()->unique()->after('is_anonymous');
            }

            if (! Schema::hasColumn('counseling_sessions', 'identity_revealed_at')) {
                $table->timestamp('identity_revealed_at')->nullable()->after('anonymous_id');
            }

            if (! Schema::hasColumn('counseling_sessions', 'identity_revealed_by')) {
                $table->foreignId('identity_revealed_by')
                    ->nullable()
                    ->after('identity_revealed_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            $table->index(['is_anonymous', 'status'], 'counseling_sessions_anonymous_status_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('counseling_sessions')) {
            return;
        }

        Schema::table('counseling_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('counseling_sessions', 'identity_revealed_by')) {
                $table->dropConstrainedForeignId('identity_revealed_by');
            }

            if (Schema::hasColumn('counseling_sessions', 'identity_revealed_at')) {
                $table->dropColumn('identity_revealed_at');
            }

            if (Schema::hasColumn('counseling_sessions', 'anonymous_id')) {
                $table->dropUnique('counseling_sessions_anonymous_id_unique');
                $table->dropColumn('anonymous_id');
            }

            if (Schema::hasColumn('counseling_sessions', 'is_anonymous')) {
                $table->dropColumn('is_anonymous');
            }

            $table->dropIndex('counseling_sessions_anonymous_status_idx');
        });
    }
};
