<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!$this->isMySql()) {
            // PR4A cutover is executed on MySQL environments only.
            return;
        }

        if (!Schema::hasTable('accounts') || !Schema::hasTable('users')) {
            return;
        }

        $targetTables = $this->tablesWithShadowActorColumns();
        $nullabilityMap = $this->captureLegacyActorNullability($targetTables);
        $droppedForeignEdges = $this->collectForeignEdgesReferencingActors();

        $this->dropForeignKeysReferencingActors($droppedForeignEdges);

        $this->cutoverPrimaryKeyToUuid('accounts');
        $this->cutoverPrimaryKeyToUuid('users');

        foreach ($targetTables as $table) {
            $this->promoteShadowActorColumn($table, 'account_id', $nullabilityMap[$table]['account_id'] ?? null);
            $this->promoteShadowActorColumn($table, 'user_id', $nullabilityMap[$table]['user_id'] ?? null);
        }

        $this->rebuildActorForeignKeys($droppedForeignEdges);

        DB::purge();
        DB::reconnect();
    }

    public function down(): void
    {
        // No-op: PR4A cutover is intentionally forward-only.
    }

    private function isMySql(): bool
    {
        return DB::getDriverName() === 'mysql';
    }

    /**
     * @return array<int, string>
     */
    private function tablesWithShadowActorColumns(): array
    {
        $tables = [];

        foreach (Schema::getTableListing() as $table) {
            if (
                Schema::hasColumn($table, 'account_id_uuid') ||
                Schema::hasColumn($table, 'user_id_uuid')
            ) {
                $tables[] = $table;
            }
        }

        sort($tables);

        return $tables;
    }

    /**
     * @param array<int, string> $tables
     * @return array<string, array{account_id: ?bool, user_id: ?bool}>
     */
    private function captureLegacyActorNullability(array $tables): array
    {
        $map = [];

        foreach ($tables as $table) {
            $map[$table] = [
                'account_id' => Schema::hasColumn($table, 'account_id')
                    ? $this->isColumnNullable($table, 'account_id')
                    : null,
                'user_id' => Schema::hasColumn($table, 'user_id')
                    ? $this->isColumnNullable($table, 'user_id')
                    : null,
            ];
        }

        return $map;
    }

    /**
     * @return array<int, array{table: string, column: string, parent: string, constraint: string, nullable: bool}>
     */
    private function collectForeignEdgesReferencingActors(): array
    {
        $rows = DB::select(
            <<<'SQL'
            SELECT
                k.TABLE_NAME AS table_name,
                k.COLUMN_NAME AS column_name,
                k.REFERENCED_TABLE_NAME AS parent_table,
                k.CONSTRAINT_NAME AS constraint_name,
                c.IS_NULLABLE AS is_nullable
            FROM information_schema.KEY_COLUMN_USAGE k
            JOIN information_schema.COLUMNS c
              ON c.TABLE_SCHEMA = k.TABLE_SCHEMA
             AND c.TABLE_NAME = k.TABLE_NAME
             AND c.COLUMN_NAME = k.COLUMN_NAME
            WHERE k.TABLE_SCHEMA = DATABASE()
              AND k.REFERENCED_TABLE_NAME IN ('accounts', 'users')
              AND k.REFERENCED_COLUMN_NAME = 'id'
            SQL
        );

        $edges = [];
        foreach ($rows as $row) {
            $edges[] = [
                'table' => (string) $row->table_name,
                'column' => (string) $row->column_name,
                'parent' => (string) $row->parent_table,
                'constraint' => (string) $row->constraint_name,
                'nullable' => strtoupper((string) $row->is_nullable) === 'YES',
            ];
        }

        return $edges;
    }

    /**
     * @param array<int, array{table: string, column: string, parent: string, constraint: string, nullable: bool}> $edges
     */
    private function dropForeignKeysReferencingActors(array $edges): void
    {
        $seen = [];

        foreach ($edges as $edge) {
            $key = $edge['table'].'|'.$edge['constraint'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            if (!Schema::hasTable($edge['table'])) {
                continue;
            }

            DB::statement(sprintf(
                'ALTER TABLE `%s` DROP FOREIGN KEY `%s`',
                $edge['table'],
                $edge['constraint']
            ));
        }
    }

    private function cutoverPrimaryKeyToUuid(string $table): void
    {
        if (
            !Schema::hasTable($table) ||
            !Schema::hasColumn($table, 'id') ||
            !Schema::hasColumn($table, 'id_uuid')
        ) {
            return;
        }

        if (!Schema::hasColumn($table, 'id_legacy')) {
            Schema::table($table, function ($blueprint): void {
                $blueprint->renameColumn('id', 'id_legacy');
            });
        }

        if (!Schema::hasColumn($table, 'id') && Schema::hasColumn($table, 'id_uuid')) {
            Schema::table($table, function ($blueprint): void {
                $blueprint->renameColumn('id_uuid', 'id');
            });
        }

        if (!Schema::hasColumn($table, 'id') || !Schema::hasColumn($table, 'id_legacy')) {
            return;
        }

        $this->ensureLegacyIdSupportIndex($table);

        if ($this->hasPrimaryKey($table)) {
            DB::statement(sprintf('ALTER TABLE `%s` DROP PRIMARY KEY', $table));
        }

        $legacyIdType = $this->mysqlColumnType($table, 'id_legacy') ?? 'bigint unsigned';
        DB::statement(sprintf('ALTER TABLE `%s` MODIFY `id_legacy` %s NULL', $table, $legacyIdType));
        DB::statement(sprintf('ALTER TABLE `%s` MODIFY `id` CHAR(36) NOT NULL', $table));
        DB::statement(sprintf('ALTER TABLE `%s` ADD PRIMARY KEY (`id`)', $table));
    }

    private function hasPrimaryKey(string $table): bool
    {
        $result = DB::selectOne(
            <<<'SQL'
            SELECT COUNT(*) AS aggregate_count
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND CONSTRAINT_TYPE = 'PRIMARY KEY'
            SQL,
            [$table]
        );

        return ((int) ($result->aggregate_count ?? 0)) > 0;
    }

    private function promoteShadowActorColumn(string $table, string $column, ?bool $nullableFromLegacy): void
    {
        $shadow = $column.'_uuid';
        $legacy = $column.'_legacy';

        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $shadow)) {
            return;
        }

        if (Schema::hasColumn($table, $column) && !Schema::hasColumn($table, $legacy)) {
            Schema::table($table, function ($blueprint) use ($column, $legacy): void {
                $blueprint->renameColumn($column, $legacy);
            });
        }

        if (!Schema::hasColumn($table, $column) && Schema::hasColumn($table, $shadow)) {
            Schema::table($table, function ($blueprint) use ($shadow, $column): void {
                $blueprint->renameColumn($shadow, $column);
            });
        }

        if (!Schema::hasColumn($table, $column)) {
            return;
        }

        if (Schema::hasColumn($table, $legacy)) {
            $legacyType = $this->mysqlColumnType($table, $legacy) ?? 'bigint unsigned';
            DB::statement(sprintf(
                'ALTER TABLE `%s` MODIFY `%s` %s NULL',
                $table,
                $legacy,
                $legacyType
            ));
        }

        $nullable = $nullableFromLegacy;
        if ($nullable === null && Schema::hasColumn($table, $legacy)) {
            $nullable = $this->isColumnNullable($table, $legacy);
        }

        if ($nullable === null) {
            $nullable = true;
        }

        DB::statement(sprintf(
            'ALTER TABLE `%s` MODIFY `%s` CHAR(36) %s',
            $table,
            $column,
            $nullable ? 'NULL' : 'NOT NULL'
        ));

        $this->ensureColumnIndex($table, $column);
    }

    private function ensureColumnIndex(string $table, string $column): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return;
        }

        $result = DB::selectOne(
            <<<'SQL'
            SELECT COUNT(*) AS aggregate_count
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
            SQL,
            [$table, $column]
        );

        if (((int) ($result->aggregate_count ?? 0)) > 0) {
            return;
        }

        $indexName = $this->generatedIndexName($table, $column);
        DB::statement(sprintf(
            'ALTER TABLE `%s` ADD INDEX `%s` (`%s`)',
            $table,
            $indexName,
            $column
        ));
    }

    private function ensureLegacyIdSupportIndex(string $table): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'id_legacy')) {
            return;
        }

        $result = DB::selectOne(
            <<<'SQL'
            SELECT COUNT(*) AS aggregate_count
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = 'id_legacy'
              AND INDEX_NAME <> 'PRIMARY'
            SQL,
            [$table]
        );

        if (((int) ($result->aggregate_count ?? 0)) > 0) {
            return;
        }

        $indexName = $this->generatedIndexName($table, 'id_legacy');
        DB::statement(sprintf(
            'ALTER TABLE `%s` ADD INDEX `%s` (`id_legacy`)',
            $table,
            $indexName
        ));
    }

    private function generatedIndexName(string $table, string $column): string
    {
        return 'idx_'.$table.'_'.$column.'_'.substr(md5($table.'_'.$column), 0, 8);
    }

    private function generatedForeignKeyName(string $table, string $column, string $parent): string
    {
        return 'fk_'.$table.'_'.$column.'_'.$parent.'_'.substr(md5($table.'_'.$column.'_'.$parent), 0, 6);
    }

    private function isColumnNullable(string $table, string $column): ?bool
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return null;
        }

        $row = DB::selectOne(
            <<<'SQL'
            SELECT IS_NULLABLE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
            LIMIT 1
            SQL,
            [$table, $column]
        );

        if ($row === null) {
            return null;
        }

        return strtoupper((string) $row->IS_NULLABLE) === 'YES';
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

    /**
     * @param array<int, array{table: string, column: string, parent: string, constraint: string, nullable: bool}> $droppedEdges
     */
    private function rebuildActorForeignKeys(array $droppedEdges): void
    {
        $targetEdges = [];

        foreach ($droppedEdges as $edge) {
            $key = $edge['table'].'|'.$edge['column'].'|'.$edge['parent'];
            $targetEdges[$key] = [
                'table' => $edge['table'],
                'column' => $edge['column'],
                'parent' => $edge['parent'],
            ];
        }

        foreach ($this->requiredActorEdges() as $edge) {
            $key = $edge['table'].'|'.$edge['column'].'|'.$edge['parent'];
            $targetEdges[$key] = $edge;
        }

        foreach ($targetEdges as $edge) {
            $table = $edge['table'];
            $column = $edge['column'];
            $parent = $edge['parent'];

            if (
                !Schema::hasTable($table) ||
                !Schema::hasTable($parent) ||
                !Schema::hasColumn($table, $column) ||
                !Schema::hasColumn($parent, 'id')
            ) {
                continue;
            }

            if (!$this->isUuidCompatibleColumn($table, $column) || !$this->isUuidCompatibleColumn($parent, 'id')) {
                continue;
            }

            $this->dropForeignKeysOnColumn($table, $column);
            $this->ensureColumnIndex($table, $column);

            $nullable = $this->isColumnNullable($table, $column) ?? true;
            $onDelete = $nullable ? 'SET NULL' : 'CASCADE';
            $constraintName = $this->generatedForeignKeyName($table, $column, $parent);

            DB::statement(sprintf(
                'ALTER TABLE `%s` ADD CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES `%s`(`id`) ON DELETE %s',
                $table,
                $constraintName,
                $column,
                $parent,
                $onDelete
            ));
        }
    }

    /**
     * @return array<int, array{table: string, column: string, parent: string}>
     */
    private function requiredActorEdges(): array
    {
        return [
            ['table' => 'users', 'column' => 'account_id', 'parent' => 'accounts'],
            ['table' => 'addresses', 'column' => 'account_id', 'parent' => 'accounts'],
            ['table' => 'claims', 'column' => 'account_id', 'parent' => 'accounts'],
            ['table' => 'invitations', 'column' => 'account_id', 'parent' => 'accounts'],
            ['table' => 'kyc_requests', 'column' => 'account_id', 'parent' => 'accounts'],
            ['table' => 'notifications', 'column' => 'account_id', 'parent' => 'accounts'],
            ['table' => 'notifications', 'column' => 'user_id', 'parent' => 'users'],
            ['table' => 'orders', 'column' => 'account_id', 'parent' => 'accounts'],
            ['table' => 'shipments', 'column' => 'account_id', 'parent' => 'accounts'],
            ['table' => 'shipments', 'column' => 'user_id', 'parent' => 'users'],
            ['table' => 'stores', 'column' => 'account_id', 'parent' => 'accounts'],
            ['table' => 'support_tickets', 'column' => 'account_id', 'parent' => 'accounts'],
            ['table' => 'support_tickets', 'column' => 'user_id', 'parent' => 'users'],
            ['table' => 'ticket_replies', 'column' => 'user_id', 'parent' => 'users'],
            ['table' => 'wallet_transactions', 'column' => 'account_id', 'parent' => 'accounts'],
            ['table' => 'wallets', 'column' => 'account_id', 'parent' => 'accounts'],
            ['table' => 'audit_logs', 'column' => 'user_id', 'parent' => 'users'],
        ];
    }

    private function isUuidCompatibleColumn(string $table, string $column): bool
    {
        $type = strtolower((string) Schema::getColumnType($table, $column));

        return !in_array($type, [
            'integer',
            'int',
            'tinyint',
            'smallint',
            'mediumint',
            'bigint',
            'biginteger',
            'unsignedbiginteger',
            'unsignedinteger',
            'decimal',
            'double',
            'float',
            'real',
            'numeric',
        ], true);
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
};
