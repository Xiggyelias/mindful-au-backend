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
            if (!Schema::hasColumn('personal_access_tokens', 'two_factor_passed_at')) {
                $table->timestamp('two_factor_passed_at')->nullable()->after('last_used_at');
                $table->index('two_factor_passed_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('personal_access_tokens')) {
            return;
        }

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            if (Schema::hasColumn('personal_access_tokens', 'two_factor_passed_at')) {
                $table->dropIndex(['two_factor_passed_at']);
                $table->dropColumn('two_factor_passed_at');
            }
        });
    }
};
