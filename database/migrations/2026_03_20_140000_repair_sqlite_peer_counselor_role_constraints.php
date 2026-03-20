<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        $this->repairUserRolesTable();
        $this->repairInstitutionAccountsTable();
    }

    public function down(): void
    {
        // Keep the repaired constraints in place. Rolling back would reintroduce
        // invalid role checks for peer counselors on SQLite.
    }

    private function repairUserRolesTable(): void
    {
        if (!Schema::hasTable('user_roles') || $this->sqliteTableSupportsRole('user_roles', 'peer_counselor')) {
            return;
        }

        $hasRoleId = Schema::hasColumn('user_roles', 'role_id');

        Schema::disableForeignKeyConstraints();

        try {
            Schema::create('user_roles__repair', function (Blueprint $table) use ($hasRoleId) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->enum('role', ['admin', 'counselor', 'peer_counselor', 'student']);

                if ($hasRoleId) {
                    $table->foreignId('role_id')->nullable()->constrained('roles')->cascadeOnDelete();
                }

                $table->boolean('approved')->default(false);
                $table->timestamps();
            });

            $columns = ['id', 'user_id', 'role'];
            if ($hasRoleId) {
                $columns[] = 'role_id';
            }
            $columns[] = 'approved';
            $columns[] = 'created_at';
            $columns[] = 'updated_at';

            DB::table('user_roles__repair')->insertUsing(
                $columns,
                DB::table('user_roles')->select($columns)
            );

            Schema::drop('user_roles');
            Schema::rename('user_roles__repair', 'user_roles');

            DB::statement(
                'CREATE UNIQUE INDEX "user_roles_user_id_role_unique" ON "user_roles" ("user_id", "role")'
            );

            if ($hasRoleId) {
                DB::statement(
                    'CREATE INDEX "user_roles_user_id_role_id_index" ON "user_roles" ("user_id", "role_id")'
                );
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    private function repairInstitutionAccountsTable(): void
    {
        if (
            !Schema::hasTable('institution_accounts')
            || $this->sqliteTableSupportsRole('institution_accounts', 'peer_counselor')
        ) {
            return;
        }

        Schema::disableForeignKeyConstraints();

        try {
            Schema::create('institution_accounts__repair', function (Blueprint $table) {
                $table->id();
                $table->string('email');
                $table->enum('role', ['student', 'staff', 'counselor', 'peer_counselor', 'admin']);
                $table->boolean('approved')->default(true);
                $table->boolean('is_active')->default(true);
                $table->string('full_name')->nullable();
                $table->string('id_number')->nullable();
                $table->timestamps();
            });

            DB::table('institution_accounts__repair')->insertUsing(
                ['id', 'email', 'role', 'approved', 'is_active', 'full_name', 'id_number', 'created_at', 'updated_at'],
                DB::table('institution_accounts')->select(
                    'id',
                    'email',
                    'role',
                    'approved',
                    'is_active',
                    'full_name',
                    'id_number',
                    'created_at',
                    'updated_at'
                )
            );

            Schema::drop('institution_accounts');
            Schema::rename('institution_accounts__repair', 'institution_accounts');

            DB::statement(
                'CREATE UNIQUE INDEX "institution_accounts_email_unique" ON "institution_accounts" ("email")'
            );
            DB::statement(
                'CREATE INDEX "institution_accounts_role_is_active_index" ON "institution_accounts" ("role", "is_active")'
            );
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    private function sqliteTableSupportsRole(string $table, string $role): bool
    {
        $definition = DB::table('sqlite_master')
            ->where('type', 'table')
            ->where('name', $table)
            ->value('sql');

        if (!is_string($definition) || trim($definition) === '') {
            return false;
        }

        return str_contains(strtolower($definition), "'" . strtolower($role) . "'");
    }
};
