<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('counseling_sessions')) {
            if (!Schema::hasColumn('counseling_sessions', 'peer_counselor_id')) {
                Schema::table('counseling_sessions', function (Blueprint $table) {
                    $table->foreignId('peer_counselor_id')
                        ->nullable()
                        ->after('counselor_id')
                        ->constrained('users')
                        ->nullOnDelete();
                    $table->index(
                        ['peer_counselor_id', 'status'],
                        'counseling_sessions_peer_counselor_status_idx'
                    );
                });
            }

            if (!Schema::hasColumn('counseling_sessions', 'assigned_by')) {
                Schema::table('counseling_sessions', function (Blueprint $table) {
                    $table->foreignId('assigned_by')
                        ->nullable()
                        ->after('peer_counselor_id')
                        ->constrained('users')
                        ->nullOnDelete();
                });
            }

            if (!Schema::hasColumn('counseling_sessions', 'assigned_role')) {
                Schema::table('counseling_sessions', function (Blueprint $table) {
                    $table->string('assigned_role', 32)->nullable()->after('assigned_by');
                    $table->index(
                        ['assigned_role', 'status'],
                        'counseling_sessions_assigned_role_status_idx'
                    );
                });
            }
        }

        if (Schema::hasTable('roles')) {
            DB::table('roles')->updateOrInsert(
                ['name' => 'peer_counselor'],
                [
                    'display_name' => 'Peer Counselor',
                    'description' => 'Supervised peer counselor with restricted case access.',
                    'color' => '#2563eb',
                    'icon' => 'Users',
                    'is_active' => true,
                    'requires_approval' => true,
                    'level' => 40,
                    'permissions' => json_encode([
                        'sessions.read_assigned',
                        'messages.chat_assigned',
                        'sessions.escalate',
                    ]),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        if (DB::getDriverName() === 'mysql') {
            if (Schema::hasTable('user_roles')) {
                DB::statement(
                    "ALTER TABLE `user_roles` MODIFY `role` ENUM('admin','counselor','peer_counselor','student') NOT NULL"
                );
            }

            if (Schema::hasTable('institution_accounts')) {
                DB::statement(
                    "ALTER TABLE `institution_accounts` MODIFY `role` ENUM('student','staff','counselor','peer_counselor','admin') NOT NULL"
                );
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('counseling_sessions')) {
            Schema::table('counseling_sessions', function (Blueprint $table) {
                if (Schema::hasColumn('counseling_sessions', 'assigned_role')) {
                    $table->dropIndex('counseling_sessions_assigned_role_status_idx');
                    $table->dropColumn('assigned_role');
                }

                if (Schema::hasColumn('counseling_sessions', 'assigned_by')) {
                    $table->dropConstrainedForeignId('assigned_by');
                }

                if (Schema::hasColumn('counseling_sessions', 'peer_counselor_id')) {
                    $table->dropIndex('counseling_sessions_peer_counselor_status_idx');
                    $table->dropConstrainedForeignId('peer_counselor_id');
                }
            });
        }
    }
};

