<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('personal_access_tokens')) {
            return;
        }

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            if (!Schema::hasColumn('personal_access_tokens', 'device_id')) {
                $table->string('device_id', 191)->nullable()->after('name');
                $table->index('device_id');
            }

            if (!Schema::hasColumn('personal_access_tokens', 'device_name')) {
                $table->string('device_name', 120)->nullable()->after('device_id');
            }

            if (!Schema::hasColumn('personal_access_tokens', 'ip_address')) {
                $table->string('ip_address', 45)->nullable()->after('last_used_at');
            }

            if (!Schema::hasColumn('personal_access_tokens', 'user_agent')) {
                $table->text('user_agent')->nullable()->after('ip_address');
            }

            if (!Schema::hasColumn('personal_access_tokens', 'last_activity_at')) {
                $table->timestamp('last_activity_at')->nullable()->after('last_used_at');
                $table->index('last_activity_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('personal_access_tokens')) {
            return;
        }

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            if (Schema::hasColumn('personal_access_tokens', 'last_activity_at')) {
                $table->dropIndex(['last_activity_at']);
                $table->dropColumn('last_activity_at');
            }

            if (Schema::hasColumn('personal_access_tokens', 'user_agent')) {
                $table->dropColumn('user_agent');
            }

            if (Schema::hasColumn('personal_access_tokens', 'ip_address')) {
                $table->dropColumn('ip_address');
            }

            if (Schema::hasColumn('personal_access_tokens', 'device_name')) {
                $table->dropColumn('device_name');
            }

            if (Schema::hasColumn('personal_access_tokens', 'device_id')) {
                $table->dropIndex(['device_id']);
                $table->dropColumn('device_id');
            }
        });
    }
};
