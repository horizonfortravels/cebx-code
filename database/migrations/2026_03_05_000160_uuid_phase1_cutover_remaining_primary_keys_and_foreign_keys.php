<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $pkTables = $this->targetRemainingPkTables();
        $fkEdges = $this->nonActorShadowEdges();
        $legacyNullability = $this->captureLegacyNullability($fkEdges);
        $legacyForeignKeys = $this->collectForeignKeysReferencingTables($pkTables);

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            $this->dropForeignKeys($legacyForeignKeys);

            foreach ($pkTables as $table) {
                $this->cutoverPrimaryKeyToUuid($table);
            }

            foreach ($fkEdges as $edge) {
                $this->promoteShadowForeignKey(
                    $edge['child_table'],
                    $edge['child_column'],
                    $legacyNullability[$edge['child_table']][$edge['child_column']] ?? null
                );
            }

            foreach ($fkEdges as $edge) {
                $this->recreateUuidForeignKey($edge);
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    public function down(): void
    {
        // No-op: PR4B cutover is intentionally forward-only.
    }

    /**
     * @return array<int, string>
     */
    private function targetRemainingPkTables(): array
    {
        $tables = [];

        foreach (Schema::getTableListing() as $table) {
            if (in_array($table, ['accounts', 'users'], true)) {
                continue;
            }

            if (
                !Schema::hasTable($table) ||
                !Schema::hasColumn($table, 'id') ||
                !Schema::hasColumn($table, 'id_uuid')
            ) {
                continue;
            }

            if (!$this->isNumericColumn($table, 'id')) {
                continue;
            }

            $tables[] = $table;
        }

        sort($tables);

        return $tables;
    }

    /**
     * @return array<int, array{child_table: string, child_column: string, parent_table: string}>
     */
    private function nonActorShadowEdges(): array
    {
        return [
            ['child_table' => 'orders', 'child_column' => 'store_id', 'parent_table' => 'stores'],
            ['child_table' => 'wallet_transactions', 'child_column' => 'wallet_id', 'parent_table' => 'wallets'],
            ['child_table' => 'shipment_events', 'child_column' => 'shipment_id', 'parent_table' => 'shipments'],
            ['child_table' => 'claims', 'child_column' => 'shipment_id', 'parent_table' => 'shipments'],
            ['child_table' => 'customs_declarations', 'child_column' => 'shipment_id', 'parent_table' => 'shipments'],
            ['child_table' => 'ticket_replies', 'child_column' => 'support_ticket_id', 'parent_table' => 'support_tickets'],
            ['child_table' => 'containers', 'child_column' => 'vessel_id', 'parent_table' => 'vessels'],
            ['child_table' => 'schedules', 'child_column' => 'vessel_id', 'parent_table' => 'vessels'],
            ['child_table' => 'risk_alerts', 'child_column' => 'risk_rule_id', 'parent_table' => 'risk_rules'],
            ['child_table' => 'branch_staff', 'child_column' => 'branch_id', 'parent_table' => 'branches'],
        ];
    }

    /**
     * @param array<int, array{child_table: string, child_column: string, parent_table: string}> $edges
     * @return array<string, array<string, ?bool>>
     */
    private function captureLegacyNullability(array $edges): array
    {
        $map = [];

        foreach ($edges as $edge) {
            $table = $edge['child_table'];
            $column = $edge['child_column'];

            if (!isset($map[$table])) {
                $map[$table] = [];
            }

            if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
                $map[$table][$column] = null;
                continue;
            }

            $map[$table][$column] = $this->isColumnNullable($table, $column);
        }

        return $map;
    }

    /**
     * @param array<int, string> $parents
     * @return array<int, array{table: string, constraint: string}>
     */
    private function collectForeignKeysReferencingTables(array $parents): array
    {
        if ($parents === []) {
            return [];
        }

        $quoted = implode(', ', array_map(
            static fn (string $table): string => "'".str_replace("'", "''", $table)."'",
            $parents
        ));

        $rows = DB::select(
            <<<SQL
            SELECT DISTINCT
                k.TABLE_NAME AS table_name,
                k.CONSTRAINT_NAME AS constraint_name
            FROM information_schema.KEY_COLUMN_USAGE k
            WHERE k.TABLE_SCHEMA = DATABASE()
              AND k.REFERENCED_TABLE_NAME IN ({$quoted})
              AND k.REFERENCED_COLUMN_NAME = 'id'
            SQL
        );

        $fks = [];
        foreach ($rows as $row) {
            $fks[] = [
                'table' => (string) $row->table_name,
                'constraint' => (string) $row->constraint_name,
            ];
        }

        return $fks;
    }

    /**
     * @param array<int, array{table: string, constraint: string}> $fks
     */
    private function dropForeignKeys(array $fks): void
    {
        $seen = [];

        foreach ($fks as $fk) {
            $key = $fk['table'].'|'.$fk['constraint'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            if (!Schema::hasTable($fk['table'])) {
                continue;
            }

            DB::statement(sprintf(
                'ALTER TABLE `%s` DROP FOREIGN KEY `%s`',
                $fk['table'],
                $fk['constraint']
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

        $this->dropNonPrimaryIndexesOnColumn($table, 'id');

        $legacyType = $this->mysqlColumnType($table, 'id_legacy') ?? 'bigint unsigned';
        DB::statement(sprintf('ALTER TABLE `%s` MODIFY `id_legacy` %s NULL', $table, $legacyType));
        DB::statement(sprintf('ALTER TABLE `%s` MODIFY `id` CHAR(36) NOT NULL', $table));
        DB::statement(sprintf('ALTER TABLE `%s` ADD PRIMARY KEY (`id`)', $table));
    }

    private function promoteShadowForeignKey(string $table, string $column, ?bool $nullableFromLegacy): void
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
            $this->ensureColumnIndex($table, $legacy);
        }

        $nullable = $nullableFromLegacy;
        if ($nullable === null && Schema::hasColumn($table, $legacy)) {
            $nullable = $this->isColumnNullable($table, $legacy);
        }

        DB::statement(sprintf(
            'ALTER TABLE `%s` MODIFY `%s` CHAR(36) %s',
            $table,
            $column,
            ($nullable ?? true) ? 'NULL' : 'NOT NULL'
        ));

        $this->ensureColumnIndex($table, $column);
    }

    /**
     * @param array{child_table: string, child_column: string, parent_table: string} $edge
     */
    private function recreateUuidForeignKey(array $edge): void
    {
        $childTable = $edge['child_table'];
        $childColumn = $edge['child_column'];
        $parentTable = $edge['parent_table'];

        if (
            !Schema::hasTable($childTable) ||
            !Schema::hasTable($parentTable) ||
            !Schema::hasColumn($childTable, $childColumn) ||
            !Schema::hasColumn($parentTable, 'id')
        ) {
            return;
        }

        if (!$this->isUuidCompatibleColumn($childTable, $childColumn) || !$this->isUuidCompatibleColumn($parentTable, 'id')) {
            return;
        }

        $this->dropForeignKeysOnColumn($childTable, $childColumn);

        $constraintName = $this->generatedForeignKeyName($childTable, $childColumn, $parentTable);
        $nullable = $this->isColumnNullable($childTable, $childColumn) ?? true;

        DB::statement(sprintf(
            'ALTER TABLE `%s` ADD CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES `%s`(`id`) ON DELETE %s',
            $childTable,
            $constraintName,
            $childColumn,
            $parentTable,
            $nullable ? 'SET NULL' : 'CASCADE'
        ));
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

    private function isNumericColumn(string $table, string $column): bool
    {
        $type = strtolower((string) Schema::getColumnType($table, $column));

        return in_array($type, [
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

    private function isUuidCompatibleColumn(string $table, string $column): bool
    {
        return !$this->isNumericColumn($table, $column);
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

    private function dropNonPrimaryIndexesOnColumn(string $table, string $column): void
    {
        $rows = DB::select(
            <<<'SQL'
            SELECT DISTINCT INDEX_NAME AS index_name
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
              AND INDEX_NAME <> 'PRIMARY'
            SQL,
            [$table, $column]
        );

        foreach ($rows as $row) {
            DB::statement(sprintf(
                'ALTER TABLE `%s` DROP INDEX `%s`',
                $table,
                (string) $row->index_name
            ));
        }
    }
};
