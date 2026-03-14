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

        $dropTargets = $this->collectCleanupDropTargets();

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($dropTargets as $target) {
                $table = $target['table'];
                $column = $target['column'];

                if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
                    continue;
                }

                $this->dropForeignKeysOnColumn($table, $column);
                $this->dropNonPrimaryIndexesOnColumn($table, $column);
                DB::statement(sprintf('ALTER TABLE `%s` DROP COLUMN `%s`', $table, $column));
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->ensureUuidPrimaryKeys();
        $this->ensureAccountIdIndexes();
        $this->rebuildMainUuidForeignKeys();
        $this->assertNoCleanupColumnsRemain();
    }

    public function down(): void
    {
        // Forward-only cleanup migration.
    }

    /**
     * @return array<int, array{table: string, column: string}>
     */
    private function collectCleanupDropTargets(): array
    {
        $targets = [];

        foreach (Schema::getTableListing() as $table) {
            foreach (Schema::getColumnListing($table) as $column) {
                if (str_ends_with($column, '_legacy')) {
                    $base = substr($column, 0, -7);

                    if (!Schema::hasColumn($table, $base)) {
                        throw new RuntimeException(sprintf(
                            'UUID Phase 1 PR5B cleanup blocked: %s.%s exists but promoted column %s is missing.',
                            $table,
                            $column,
                            $base
                        ));
                    }

                    if (!$this->isUuidLength36Column($table, $base)) {
                        throw new RuntimeException(sprintf(
                            'UUID Phase 1 PR5B cleanup blocked: %s.%s is not UUID-compatible.',
                            $table,
                            $base
                        ));
                    }

                    $targets[$table.'.'.$column] = ['table' => $table, 'column' => $column];
                    continue;
                }

                if (str_ends_with($column, '_uuid')) {
                    $base = substr($column, 0, -5);

                    // Drop only promoted shadow columns (base column already exists).
                    if (!Schema::hasColumn($table, $base)) {
                        continue;
                    }

                    if (!$this->isUuidLength36Column($table, $base)) {
                        throw new RuntimeException(sprintf(
                            'UUID Phase 1 PR5B cleanup blocked: %s.%s is not UUID-compatible.',
                            $table,
                            $base
                        ));
                    }

                    $targets[$table.'.'.$column] = ['table' => $table, 'column' => $column];
                }
            }
        }

        ksort($targets);

        return array_values($targets);
    }

    private function ensureUuidPrimaryKeys(): void
    {
        foreach (Schema::getTableListing() as $table) {
            if ($this->isSystemTableWithNonUuidId($table) || !Schema::hasColumn($table, 'id')) {
                continue;
            }

            if ($this->isNumericColumn($table, 'id')) {
                throw new RuntimeException(sprintf(
                    'UUID Phase 1 PR5B cleanup blocked: %s.id is still numeric.',
                    $table
                ));
            }

            if (!$this->tableHasPrimaryKeyOnId($table)) {
                if ($this->tableHasAnyPrimaryKey($table)) {
                    throw new RuntimeException(sprintf(
                        'UUID Phase 1 PR5B cleanup blocked: %s has a primary key not using id.',
                        $table
                    ));
                }

                DB::statement(sprintf('ALTER TABLE `%s` ADD PRIMARY KEY (`id`)', $table));
            }

            DB::statement(sprintf('ALTER TABLE `%s` MODIFY `id` CHAR(36) NOT NULL', $table));
        }
    }

    private function ensureAccountIdIndexes(): void
    {
        foreach (Schema::getTableListing() as $table) {
            if (!Schema::hasColumn($table, 'account_id')) {
                continue;
            }

            if ($this->isNumericColumn($table, 'account_id')) {
                throw new RuntimeException(sprintf(
                    'UUID Phase 1 PR5B cleanup blocked: %s.account_id is still numeric.',
                    $table
                ));
            }

            $nullable = $this->isColumnNullable($table, 'account_id');
            DB::statement(sprintf(
                'ALTER TABLE `%s` MODIFY `account_id` CHAR(36) %s',
                $table,
                $nullable ? 'NULL' : 'NOT NULL'
            ));
            $this->ensureColumnIndex($table, 'account_id');
        }
    }

    private function rebuildMainUuidForeignKeys(): void
    {
        foreach ($this->mainForeignKeyEdges() as $edge) {
            $childTable = $edge['child_table'];
            $childColumn = $edge['child_column'];
            $parentTable = $edge['parent_table'];
            $nullable = (bool) $edge['nullable'];

            if (
                !Schema::hasTable($childTable) ||
                !Schema::hasTable($parentTable) ||
                !Schema::hasColumn($childTable, $childColumn) ||
                !Schema::hasColumn($parentTable, 'id')
            ) {
                continue;
            }

            if ($this->isNumericColumn($childTable, $childColumn) || $this->isNumericColumn($parentTable, 'id')) {
                throw new RuntimeException(sprintf(
                    'UUID Phase 1 PR5B cleanup blocked: FK edge %s.%s -> %s.id is not UUID-compatible.',
                    $childTable,
                    $childColumn,
                    $parentTable
                ));
            }

            DB::statement(sprintf(
                'ALTER TABLE `%s` MODIFY `%s` CHAR(36) %s',
                $childTable,
                $childColumn,
                $nullable ? 'NULL' : 'NOT NULL'
            ));

            $this->dropForeignKeysOnColumn($childTable, $childColumn);
            $this->ensureColumnIndex($childTable, $childColumn);

            $constraint = $this->generatedForeignKeyName($childTable, $childColumn, $parentTable);
            DB::statement(sprintf(
                'ALTER TABLE `%s` ADD CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES `%s`(`id`) ON DELETE %s',
                $childTable,
                $constraint,
                $childColumn,
                $parentTable,
                $nullable ? 'SET NULL' : 'CASCADE'
            ));
        }
    }

    private function assertNoCleanupColumnsRemain(): void
    {
        $legacyColumns = DB::select(
            <<<'SQL'
            SELECT TABLE_NAME AS table_name, COLUMN_NAME AS column_name
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND COLUMN_NAME LIKE '%\_legacy'
            ORDER BY TABLE_NAME, COLUMN_NAME
            SQL
        );

        if ($legacyColumns !== []) {
            $samples = [];
            foreach (array_slice($legacyColumns, 0, 10) as $column) {
                $samples[] = $column->table_name.'.'.$column->column_name;
            }

            throw new RuntimeException(
                'UUID Phase 1 PR5B cleanup failed: legacy columns still exist. Samples: '
                .implode(', ', $samples)
            );
        }

        $shadowColumns = DB::select(
            <<<'SQL'
            SELECT TABLE_NAME AS table_name, COLUMN_NAME AS column_name
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND COLUMN_NAME LIKE '%\_uuid'
            ORDER BY TABLE_NAME, COLUMN_NAME
            SQL
        );

        $promotedShadows = [];
        foreach ($shadowColumns as $column) {
            $table = (string) $column->table_name;
            $shadow = (string) $column->column_name;
            $base = substr($shadow, 0, -5);

            if (Schema::hasTable($table) && Schema::hasColumn($table, $base)) {
                $promotedShadows[] = $table.'.'.$shadow;
            }
        }

        if ($promotedShadows !== []) {
            throw new RuntimeException(
                'UUID Phase 1 PR5B cleanup failed: promoted shadow columns still exist. Samples: '
                .implode(', ', array_slice($promotedShadows, 0, 10))
            );
        }
    }

    /**
     * @return array<int, array{
     *   child_table: string,
     *   child_column: string,
     *   parent_table: string,
     *   nullable: bool
     * }>
     */
    private function mainForeignKeyEdges(): array
    {
        return [
            ['child_table' => 'users', 'child_column' => 'account_id', 'parent_table' => 'accounts', 'nullable' => false],
            ['child_table' => 'addresses', 'child_column' => 'account_id', 'parent_table' => 'accounts', 'nullable' => false],
            ['child_table' => 'claims', 'child_column' => 'account_id', 'parent_table' => 'accounts', 'nullable' => true],
            ['child_table' => 'invitations', 'child_column' => 'account_id', 'parent_table' => 'accounts', 'nullable' => false],
            ['child_table' => 'kyc_requests', 'child_column' => 'account_id', 'parent_table' => 'accounts', 'nullable' => false],
            ['child_table' => 'notifications', 'child_column' => 'account_id', 'parent_table' => 'accounts', 'nullable' => false],
            ['child_table' => 'notifications', 'child_column' => 'user_id', 'parent_table' => 'users', 'nullable' => true],
            ['child_table' => 'orders', 'child_column' => 'account_id', 'parent_table' => 'accounts', 'nullable' => false],
            ['child_table' => 'shipments', 'child_column' => 'account_id', 'parent_table' => 'accounts', 'nullable' => false],
            ['child_table' => 'shipments', 'child_column' => 'user_id', 'parent_table' => 'users', 'nullable' => true],
            ['child_table' => 'stores', 'child_column' => 'account_id', 'parent_table' => 'accounts', 'nullable' => false],
            ['child_table' => 'support_tickets', 'child_column' => 'account_id', 'parent_table' => 'accounts', 'nullable' => false],
            ['child_table' => 'support_tickets', 'child_column' => 'user_id', 'parent_table' => 'users', 'nullable' => false],
            ['child_table' => 'ticket_replies', 'child_column' => 'user_id', 'parent_table' => 'users', 'nullable' => false],
            ['child_table' => 'wallet_transactions', 'child_column' => 'account_id', 'parent_table' => 'accounts', 'nullable' => false],
            ['child_table' => 'wallets', 'child_column' => 'account_id', 'parent_table' => 'accounts', 'nullable' => false],
            ['child_table' => 'audit_logs', 'child_column' => 'user_id', 'parent_table' => 'users', 'nullable' => true],
            ['child_table' => 'orders', 'child_column' => 'store_id', 'parent_table' => 'stores', 'nullable' => true],
            ['child_table' => 'wallet_transactions', 'child_column' => 'wallet_id', 'parent_table' => 'wallets', 'nullable' => false],
            ['child_table' => 'ticket_replies', 'child_column' => 'support_ticket_id', 'parent_table' => 'support_tickets', 'nullable' => false],
            ['child_table' => 'shipment_events', 'child_column' => 'shipment_id', 'parent_table' => 'shipments', 'nullable' => false],
            ['child_table' => 'claims', 'child_column' => 'shipment_id', 'parent_table' => 'shipments', 'nullable' => true],
            ['child_table' => 'customs_declarations', 'child_column' => 'shipment_id', 'parent_table' => 'shipments', 'nullable' => true],
            ['child_table' => 'containers', 'child_column' => 'vessel_id', 'parent_table' => 'vessels', 'nullable' => true],
            ['child_table' => 'schedules', 'child_column' => 'vessel_id', 'parent_table' => 'vessels', 'nullable' => true],
            ['child_table' => 'risk_alerts', 'child_column' => 'risk_rule_id', 'parent_table' => 'risk_rules', 'nullable' => true],
            ['child_table' => 'branch_staff', 'child_column' => 'branch_id', 'parent_table' => 'branches', 'nullable' => false],
            ['child_table' => 'user_role', 'child_column' => 'user_id', 'parent_table' => 'users', 'nullable' => false],
            ['child_table' => 'user_role', 'child_column' => 'assigned_by', 'parent_table' => 'users', 'nullable' => true],
            ['child_table' => 'user_role', 'child_column' => 'role_id', 'parent_table' => 'roles', 'nullable' => false],
            ['child_table' => 'role_permission', 'child_column' => 'role_id', 'parent_table' => 'roles', 'nullable' => false],
            ['child_table' => 'role_permission', 'child_column' => 'permission_id', 'parent_table' => 'permissions', 'nullable' => false],
        ];
    }

    private function dropForeignKeysOnColumn(string $table, string $column): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return;
        }

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

    private function dropNonPrimaryIndexesOnColumn(string $table, string $column): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return;
        }

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

    private function generatedIndexName(string $table, string $column): string
    {
        return 'idx_'.$table.'_'.$column.'_'.substr(md5($table.'_'.$column), 0, 8);
    }

    private function generatedForeignKeyName(string $table, string $column, string $parent): string
    {
        return 'fk_'.$table.'_'.$column.'_'.$parent.'_'.substr(md5($table.'_'.$column.'_'.$parent), 0, 6);
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

    private function isUuidLength36Column(string $table, string $column): bool
    {
        $columnType = strtolower((string) $this->mysqlColumnType($table, $column));

        return str_contains($columnType, 'char(36)') || str_contains($columnType, 'varchar(36)');
    }

    private function tableHasAnyPrimaryKey(string $table): bool
    {
        $row = DB::selectOne(
            <<<'SQL'
            SELECT COUNT(*) AS aggregate_count
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND CONSTRAINT_TYPE = 'PRIMARY KEY'
            SQL,
            [$table]
        );

        return ((int) ($row->aggregate_count ?? 0)) > 0;
    }

    private function tableHasPrimaryKeyOnId(string $table): bool
    {
        $row = DB::selectOne(
            <<<'SQL'
            SELECT COUNT(*) AS aggregate_count
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND CONSTRAINT_NAME = 'PRIMARY'
              AND COLUMN_NAME = 'id'
            SQL,
            [$table]
        );

        return ((int) ($row->aggregate_count ?? 0)) > 0;
    }

    private function isColumnNullable(string $table, string $column): bool
    {
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

        if ($row === null || !isset($row->IS_NULLABLE)) {
            return true;
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

    private function isSystemTableWithNonUuidId(string $table): bool
    {
        return in_array($table, [
            'cache',
            'cache_locks',
            'failed_jobs',
            'job_batches',
            'jobs',
            'migrations',
            'password_reset_tokens',
            'personal_access_tokens',
            'sessions',
        ], true);
    }
};
