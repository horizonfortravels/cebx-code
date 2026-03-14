<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addPermissionsAudienceColumn();
        $this->createInternalRolesTable();
        $this->createInternalRolePermissionTable();
        $this->createInternalUserRoleTable();
        $this->ensureInternalRolePermissionForeignKeys();
        $this->ensureInternalUserRoleForeignKeys();
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_user_role');
        Schema::dropIfExists('internal_role_permission');
        Schema::dropIfExists('internal_roles');

        if (Schema::hasTable('permissions') && Schema::hasColumn('permissions', 'audience')) {
            $this->dropIndexByName('permissions', 'permissions_audience_index');

            Schema::table('permissions', function (Blueprint $table): void {
                $table->dropColumn('audience');
            });
        }
    }

    private function addPermissionsAudienceColumn(): void
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }

        if (!Schema::hasColumn('permissions', 'audience')) {
            Schema::table('permissions', function (Blueprint $table): void {
                $table->enum('audience', ['internal', 'external', 'both'])
                    ->default('both')
                    ->after('description');
            });
        }

        if (!$this->indexExistsByName('permissions', 'permissions_audience_index')) {
            Schema::table('permissions', function (Blueprint $table): void {
                $table->index('audience', 'permissions_audience_index');
            });
        }
    }

    private function createInternalRolesTable(): void
    {
        if (Schema::hasTable('internal_roles')) {
            return;
        }

        Schema::create('internal_roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 100)->unique();
            $table->string('display_name', 150);
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function createInternalRolePermissionTable(): void
    {
        if (Schema::hasTable('internal_role_permission')) {
            return;
        }

        Schema::create('internal_role_permission', function (Blueprint $table): void {
            $table->uuid('internal_role_id');
            $table->uuid('permission_id');
            $table->timestamp('granted_at')->useCurrent();

            $table->primary(['internal_role_id', 'permission_id'], 'internal_role_permission_pk');
            $table->index('permission_id', 'internal_role_permission_permission_id_index');
        });
    }

    private function createInternalUserRoleTable(): void
    {
        if (Schema::hasTable('internal_user_role')) {
            return;
        }

        Schema::create('internal_user_role', function (Blueprint $table): void {
            $table->uuid('user_id');
            $table->uuid('internal_role_id');
            $table->uuid('assigned_by')->nullable();
            $table->timestamp('assigned_at')->useCurrent();

            $table->primary(['user_id', 'internal_role_id'], 'internal_user_role_pk');
            $table->index('internal_role_id', 'internal_user_role_internal_role_id_index');
            $table->index('assigned_by', 'internal_user_role_assigned_by_index');
        });
    }

    private function ensureInternalRolePermissionForeignKeys(): void
    {
        if (!Schema::hasTable('internal_role_permission')) {
            return;
        }

        if (Schema::hasTable('internal_roles') &&
            Schema::hasColumn('internal_role_permission', 'internal_role_id') &&
            !$this->foreignKeyExists('internal_role_permission', 'internal_role_permission_internal_role_id_foreign')) {
            Schema::table('internal_role_permission', function (Blueprint $table): void {
                $table->foreign('internal_role_id', 'internal_role_permission_internal_role_id_foreign')
                    ->references('id')
                    ->on('internal_roles')
                    ->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('permissions') &&
            Schema::hasColumn('internal_role_permission', 'permission_id') &&
            !$this->foreignKeyExists('internal_role_permission', 'internal_role_permission_permission_id_foreign')) {
            Schema::table('internal_role_permission', function (Blueprint $table): void {
                $table->foreign('permission_id', 'internal_role_permission_permission_id_foreign')
                    ->references('id')
                    ->on('permissions')
                    ->cascadeOnDelete();
            });
        }
    }

    private function ensureInternalUserRoleForeignKeys(): void
    {
        if (!Schema::hasTable('internal_user_role')) {
            return;
        }

        if (Schema::hasTable('users') &&
            Schema::hasColumn('internal_user_role', 'user_id') &&
            !$this->foreignKeyExists('internal_user_role', 'internal_user_role_user_id_foreign')) {
            Schema::table('internal_user_role', function (Blueprint $table): void {
                $table->foreign('user_id', 'internal_user_role_user_id_foreign')
                    ->references('id')
                    ->on('users')
                    ->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('internal_roles') &&
            Schema::hasColumn('internal_user_role', 'internal_role_id') &&
            !$this->foreignKeyExists('internal_user_role', 'internal_user_role_internal_role_id_foreign')) {
            Schema::table('internal_user_role', function (Blueprint $table): void {
                $table->foreign('internal_role_id', 'internal_user_role_internal_role_id_foreign')
                    ->references('id')
                    ->on('internal_roles')
                    ->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('users') &&
            Schema::hasColumn('internal_user_role', 'assigned_by') &&
            !$this->foreignKeyExists('internal_user_role', 'internal_user_role_assigned_by_foreign')) {
            Schema::table('internal_user_role', function (Blueprint $table): void {
                $table->foreign('assigned_by', 'internal_user_role_assigned_by_foreign')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }
    }

    private function indexExistsByName(string $table, string $index): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            $row = DB::selectOne(
                <<<'SQL'
                SELECT COUNT(*) AS aggregate_count
                FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND INDEX_NAME = ?
                SQL,
                [$table, $index]
            );

            return ((int) ($row->aggregate_count ?? 0)) > 0;
        }

        if ($driver === 'sqlite') {
            $rows = DB::select(sprintf('PRAGMA index_list("%s")', $table));
            foreach ($rows as $row) {
                if (($row->name ?? null) === $index) {
                    return true;
                }
            }
        }

        return false;
    }

    private function foreignKeyExists(string $table, string $constraintName): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            $row = DB::selectOne(
                <<<'SQL'
                SELECT COUNT(*) AS aggregate_count
                FROM information_schema.TABLE_CONSTRAINTS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND CONSTRAINT_NAME = ?
                  AND CONSTRAINT_TYPE = 'FOREIGN KEY'
                SQL,
                [$table, $constraintName]
            );

            return ((int) ($row->aggregate_count ?? 0)) > 0;
        }

        if ($driver === 'sqlite') {
            $rows = DB::select(sprintf('PRAGMA foreign_key_list("%s")', $table));
            foreach ($rows as $row) {
                if (($row->id ?? null) !== null) {
                    // SQLite does not preserve named constraints reliably.
                    return false;
                }
            }
        }

        return false;
    }

    private function dropIndexByName(string $table, string $index): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement(sprintf('ALTER TABLE `%s` DROP INDEX `%s`', $table, $index));
            return;
        }

        if ($driver === 'sqlite') {
            DB::statement(sprintf('DROP INDEX IF EXISTS "%s"', $index));
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($index): void {
            $blueprint->dropIndex($index);
        });
    }
};
