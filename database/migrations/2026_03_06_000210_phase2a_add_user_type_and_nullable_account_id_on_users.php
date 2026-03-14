<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        $this->addUserTypeColumn();
        $this->ensureUserTypeIndex();
        $this->makeAccountIdNullable();
    }

    public function down(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        if (Schema::hasColumn('users', 'user_type')) {
            $this->dropIndexByName('users', 'users_user_type_index');

            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('user_type');
            });
        }

        // NOTE: account_id nullability rollback is intentionally omitted.
    }

    private function addUserTypeColumn(): void
    {
        if (Schema::hasColumn('users', 'user_type')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('user_type', 16)->default('external')->after('account_id');
        });
    }

    private function ensureUserTypeIndex(): void
    {
        if (!Schema::hasColumn('users', 'user_type')) {
            return;
        }

        if ($this->indexExistsByName('users', 'users_user_type_index')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->index('user_type', 'users_user_type_index');
        });
    }

    private function makeAccountIdNullable(): void
    {
        if (!Schema::hasColumn('users', 'account_id')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            $this->makeAccountIdNullableForMySql();
            return;
        }

        $accountIdType = strtolower((string) Schema::getColumnType('users', 'account_id'));
        $numericTypes = [
            'integer',
            'int',
            'tinyint',
            'smallint',
            'mediumint',
            'bigint',
            'biginteger',
            'unsignedinteger',
            'unsignedbiginteger',
        ];

        Schema::table('users', function (Blueprint $table) use ($accountIdType, $numericTypes): void {
            if (in_array($accountIdType, $numericTypes, true)) {
                $table->unsignedBigInteger('account_id')->nullable()->change();
                return;
            }

            $table->uuid('account_id')->nullable()->change();
        });
    }

    private function makeAccountIdNullableForMySql(): void
    {
        $this->dropForeignKeysOnColumn('users', 'account_id');

        $columnType = $this->mysqlColumnType('users', 'account_id') ?? 'char(36)';
        DB::statement(sprintf(
            'ALTER TABLE `users` MODIFY `account_id` %s NULL',
            $columnType
        ));

        if (!Schema::hasTable('accounts') || !Schema::hasColumn('accounts', 'id')) {
            return;
        }

        DB::statement(
            'ALTER TABLE `users` ADD CONSTRAINT `users_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts`(`id`) ON DELETE CASCADE'
        );
    }

    private function dropForeignKeysOnColumn(string $table, string $column): void
    {
        $rows = DB::select(
            <<<'SQL'
            SELECT DISTINCT CONSTRAINT_NAME AS constraint_name
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
              AND REFERENCED_TABLE_NAME IS NOT NULL
            SQL,
            [$table, $column]
        );

        foreach ($rows as $row) {
            DB::statement(sprintf(
                'ALTER TABLE `%s` DROP FOREIGN KEY `%s`',
                $table,
                (string) $row->constraint_name
            ));
        }
    }

    private function dropIndexByName(string $table, string $index): void
    {
        if (!$this->indexExistsByName($table, $index)) {
            return;
        }

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

            return false;
        }

        return false;
    }

    private function mysqlColumnType(string $table, string $column): ?string
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return null;
        }

        $row = DB::selectOne(
            <<<'SQL'
            SELECT COLUMN_TYPE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
            LIMIT 1
            SQL,
            [$table, $column]
        );

        if ($row === null || !isset($row->COLUMN_TYPE)) {
            return null;
        }

        return (string) $row->COLUMN_TYPE;
    }
};
