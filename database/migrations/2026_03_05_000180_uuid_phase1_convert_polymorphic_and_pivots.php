<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            $this->convertPersonalAccessTokens();
            $this->convertUserRolePivotColumns();
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->ensurePivotForeignKeys();
    }

    public function down(): void
    {
        // No-op: PR5A conversion is forward-only.
    }

    private function convertPersonalAccessTokens(): void
    {
        if (
            !Schema::hasTable('personal_access_tokens') ||
            !Schema::hasColumn('personal_access_tokens', 'tokenable_type') ||
            !Schema::hasColumn('personal_access_tokens', 'tokenable_id') ||
            !Schema::hasTable('users') ||
            !Schema::hasColumn('users', 'id')
        ) {
            return;
        }

        if (!Schema::hasColumn('personal_access_tokens', 'tokenable_id_uuid')) {
            Schema::table('personal_access_tokens', function (Blueprint $table): void {
                $table->char('tokenable_id_uuid', 36)->nullable()->after('tokenable_id');
            });
        }

        $this->ensureColumnIndex('personal_access_tokens', 'tokenable_id_uuid');

        if (Schema::hasColumn('users', 'id_legacy')) {
            DB::statement(
                <<<'SQL'
                UPDATE `personal_access_tokens` pat
                JOIN `users` u ON CAST(pat.`tokenable_id` AS CHAR) = CAST(u.`id_legacy` AS CHAR)
                SET pat.`tokenable_id_uuid` = u.`id`
                WHERE pat.`tokenable_type` = 'App\\Models\\User'
                  AND pat.`tokenable_id` IS NOT NULL
                  AND (pat.`tokenable_id_uuid` IS NULL OR TRIM(CAST(pat.`tokenable_id_uuid` AS CHAR)) = '')
                SQL
            );
        }

        DB::statement(
            <<<'SQL'
            UPDATE `personal_access_tokens` pat
            JOIN `users` u ON CAST(pat.`tokenable_id` AS CHAR) = CAST(u.`id` AS CHAR)
            SET pat.`tokenable_id_uuid` = u.`id`
            WHERE pat.`tokenable_type` = 'App\\Models\\User'
              AND pat.`tokenable_id` IS NOT NULL
              AND (pat.`tokenable_id_uuid` IS NULL OR TRIM(CAST(pat.`tokenable_id_uuid` AS CHAR)) = '')
            SQL
        );

        DB::statement(
            <<<'SQL'
            UPDATE `personal_access_tokens`
            SET `tokenable_id_uuid` = LEFT(TRIM(CAST(`tokenable_id` AS CHAR)), 36)
            WHERE `tokenable_id` IS NOT NULL
              AND TRIM(CAST(`tokenable_id` AS CHAR)) <> ''
              AND (`tokenable_id_uuid` IS NULL OR TRIM(CAST(`tokenable_id_uuid` AS CHAR)) = '')
            SQL
        );

        $unmappedUserTokenCount = (int) DB::table('personal_access_tokens as pat')
            ->where('pat.tokenable_type', 'App\\Models\\User')
            ->whereNotNull('pat.tokenable_id')
            ->where(function ($query): void {
                $query->whereNull('pat.tokenable_id_uuid')
                    ->orWhereRaw("TRIM(CAST(pat.tokenable_id_uuid AS CHAR)) = ''");
            })
            ->count();

        if ($unmappedUserTokenCount > 0) {
            $samples = $this->sampleDistinctValues(
                DB::table('personal_access_tokens as pat')
                    ->where('pat.tokenable_type', 'App\\Models\\User')
                    ->whereNotNull('pat.tokenable_id')
                    ->where(function ($query): void {
                        $query->whereNull('pat.tokenable_id_uuid')
                            ->orWhereRaw("TRIM(CAST(pat.tokenable_id_uuid AS CHAR)) = ''");
                    }),
                'pat.tokenable_id'
            );

            throw new RuntimeException(sprintf(
                'UUID Phase 1 PR5A failed: personal_access_tokens.tokenable_id has %d unmapped User token reference(s). sample_ids=[%s]',
                $unmappedUserTokenCount,
                implode(', ', $samples)
            ));
        }

        $this->dropForeignKeysOnColumn('personal_access_tokens', 'tokenable_id');
        $this->dropForeignKeysOnColumn('personal_access_tokens', 'tokenable_id_uuid');

        if (Schema::hasColumn('personal_access_tokens', 'tokenable_id') && !Schema::hasColumn('personal_access_tokens', 'tokenable_id_legacy')) {
            $this->renameColumnWithCurrentType('personal_access_tokens', 'tokenable_id', 'tokenable_id_legacy');
        }

        if (!Schema::hasColumn('personal_access_tokens', 'tokenable_id') && Schema::hasColumn('personal_access_tokens', 'tokenable_id_uuid')) {
            $this->renameColumnWithCurrentType('personal_access_tokens', 'tokenable_id_uuid', 'tokenable_id');
        }

        if (Schema::hasColumn('personal_access_tokens', 'tokenable_id_legacy')) {
            $legacyType = $this->mysqlColumnType('personal_access_tokens', 'tokenable_id_legacy') ?? 'varchar(255)';
            DB::statement(sprintf(
                'ALTER TABLE `personal_access_tokens` MODIFY `tokenable_id_legacy` %s NULL',
                $legacyType
            ));
            $this->ensureColumnIndex('personal_access_tokens', 'tokenable_id_legacy');
        }

        if (Schema::hasColumn('personal_access_tokens', 'tokenable_id')) {
            DB::statement('ALTER TABLE `personal_access_tokens` MODIFY `tokenable_id` CHAR(36) NOT NULL');
        }

        $this->dropCompositeIndex('personal_access_tokens', ['tokenable_type', 'tokenable_id']);
        $this->dropCompositeIndex('personal_access_tokens', ['tokenable_type', 'tokenable_id_legacy']);

        if (
            Schema::hasColumn('personal_access_tokens', 'tokenable_type') &&
            Schema::hasColumn('personal_access_tokens', 'tokenable_id')
        ) {
            $indexName = 'personal_access_tokens_tokenable_type_tokenable_id_index';
            if (!$this->indexExistsByName('personal_access_tokens', $indexName)) {
                DB::statement(sprintf(
                    'ALTER TABLE `personal_access_tokens` ADD INDEX `%s` (`tokenable_type`, `tokenable_id`)',
                    $indexName
                ));
            }
        }
    }

    private function convertUserRolePivotColumns(): void
    {
        if (!Schema::hasTable('user_role')) {
            return;
        }

        $this->promotePivotColumnWithShadow(
            table: 'user_role',
            column: 'user_id',
            parentTable: 'users',
            nullable: false
        );

        $this->promotePivotColumnWithShadow(
            table: 'user_role',
            column: 'assigned_by',
            parentTable: 'users',
            nullable: true
        );

        if (Schema::hasColumn('user_role', 'user_id') && Schema::hasColumn('user_role', 'role_id')) {
            $this->dropPrimaryKeyIfExists('user_role');
            DB::statement('ALTER TABLE `user_role` ADD PRIMARY KEY (`user_id`, `role_id`)');
        }
    }

    private function promotePivotColumnWithShadow(string $table, string $column, string $parentTable, bool $nullable): void
    {
        $shadow = $column.'_uuid';
        $legacy = $column.'_legacy';

        if (
            !Schema::hasTable($table) ||
            !Schema::hasColumn($table, $column) ||
            !Schema::hasTable($parentTable) ||
            !Schema::hasColumn($parentTable, 'id')
        ) {
            return;
        }

        if (!Schema::hasColumn($table, $shadow)) {
            Schema::table($table, function (Blueprint $blueprint) use ($column, $shadow): void {
                $blueprint->char($shadow, 36)->nullable()->after($column);
            });
        }

        $this->ensureColumnIndex($table, $shadow);

        if (Schema::hasColumn($parentTable, 'id_legacy')) {
            DB::statement(sprintf(
                <<<'SQL'
                UPDATE `%s` child
                JOIN `%s` parent ON CAST(child.`%s` AS CHAR) = CAST(parent.`id_legacy` AS CHAR)
                SET child.`%s` = parent.`id`
                WHERE child.`%s` IS NOT NULL
                  AND TRIM(CAST(child.`%s` AS CHAR)) <> ''
                  AND (child.`%s` IS NULL OR TRIM(CAST(child.`%s` AS CHAR)) = '')
                SQL,
                $table,
                $parentTable,
                $column,
                $shadow,
                $column,
                $column,
                $shadow,
                $shadow
            ));
        }

        DB::statement(sprintf(
            <<<'SQL'
            UPDATE `%s` child
            JOIN `%s` parent ON CAST(child.`%s` AS CHAR) = CAST(parent.`id` AS CHAR)
            SET child.`%s` = parent.`id`
            WHERE child.`%s` IS NOT NULL
              AND TRIM(CAST(child.`%s` AS CHAR)) <> ''
              AND (child.`%s` IS NULL OR TRIM(CAST(child.`%s` AS CHAR)) = '')
            SQL,
            $table,
            $parentTable,
            $column,
            $shadow,
            $column,
            $column,
            $shadow,
            $shadow
        ));

        $unmappedCount = (int) DB::table($table.' as child')
            ->whereNotNull('child.'.$column)
            ->whereRaw('TRIM(CAST(child.`'.$column.'` AS CHAR)) <> ""')
            ->where(function ($query) use ($shadow): void {
                $query->whereNull('child.'.$shadow)
                    ->orWhereRaw('TRIM(CAST(child.`'.$shadow.'` AS CHAR)) = ""');
            })
            ->count();

        if ($unmappedCount > 0) {
            $samples = $this->sampleDistinctValues(
                DB::table($table.' as child')
                    ->whereNotNull('child.'.$column)
                    ->whereRaw('TRIM(CAST(child.`'.$column.'` AS CHAR)) <> ""')
                    ->where(function ($query) use ($shadow): void {
                        $query->whereNull('child.'.$shadow)
                            ->orWhereRaw('TRIM(CAST(child.`'.$shadow.'` AS CHAR)) = ""');
                    }),
                'child.'.$column
            );

            throw new RuntimeException(sprintf(
                'UUID Phase 1 PR5A failed: %s.%s has %d unmapped reference(s). sample_ids=[%s]',
                $table,
                $column,
                $unmappedCount,
                implode(', ', $samples)
            ));
        }

        if (!$nullable) {
            $nullShadowCount = (int) DB::table($table)
                ->whereNull($shadow)
                ->count();

            if ($nullShadowCount > 0) {
                throw new RuntimeException(sprintf(
                    'UUID Phase 1 PR5A failed: %s.%s contains %d NULL value(s) on a required edge.',
                    $table,
                    $shadow,
                    $nullShadowCount
                ));
            }
        }

        $this->dropForeignKeysOnColumn($table, $column);
        $this->dropForeignKeysOnColumn($table, $shadow);

        if (Schema::hasColumn($table, $column) && !Schema::hasColumn($table, $legacy)) {
            $this->renameColumnWithCurrentType($table, $column, $legacy);
        }

        if (!Schema::hasColumn($table, $column) && Schema::hasColumn($table, $shadow)) {
            $this->renameColumnWithCurrentType($table, $shadow, $column);
        }

        if (Schema::hasColumn($table, $legacy)) {
            $legacyType = $this->mysqlColumnType($table, $legacy) ?? 'varchar(255)';
            DB::statement(sprintf(
                'ALTER TABLE `%s` MODIFY `%s` %s NULL',
                $table,
                $legacy,
                $legacyType
            ));
            $this->ensureColumnIndex($table, $legacy);
        }

        if (Schema::hasColumn($table, $column)) {
            DB::statement(sprintf(
                'ALTER TABLE `%s` MODIFY `%s` CHAR(36) %s',
                $table,
                $column,
                $nullable ? 'NULL' : 'NOT NULL'
            ));
            $this->ensureColumnIndex($table, $column);
        }
    }

    private function ensurePivotForeignKeys(): void
    {
        $this->ensureForeignKey('user_role', 'user_id', 'users', false);
        $this->ensureForeignKey('user_role', 'role_id', 'roles', false);
        $this->ensureForeignKey('user_role', 'assigned_by', 'users', true);
        $this->ensureForeignKey('role_permission', 'role_id', 'roles', false);
        $this->ensureForeignKey('role_permission', 'permission_id', 'permissions', false);
    }

    private function ensureForeignKey(string $table, string $column, string $parentTable, bool $nullable): void
    {
        if (
            !Schema::hasTable($table) ||
            !Schema::hasTable($parentTable) ||
            !Schema::hasColumn($table, $column) ||
            !Schema::hasColumn($parentTable, 'id')
        ) {
            return;
        }

        if ($this->isNumericColumn($table, $column) || $this->isNumericColumn($parentTable, 'id')) {
            return;
        }

        $this->dropForeignKeysOnColumn($table, $column);
        $this->ensureColumnIndex($table, $column);

        $constraintName = $this->generatedForeignKeyName($table, $column, $parentTable);

        DB::statement(sprintf(
            'ALTER TABLE `%s` ADD CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES `%s`(`id`) ON DELETE %s',
            $table,
            $constraintName,
            $column,
            $parentTable,
            $nullable ? 'SET NULL' : 'CASCADE'
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

    private function dropPrimaryKeyIfExists(string $table): void
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

        if (((int) ($result->aggregate_count ?? 0)) === 0) {
            return;
        }

        DB::statement(sprintf('ALTER TABLE `%s` DROP PRIMARY KEY', $table));
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

    private function renameColumnWithCurrentType(string $table, string $from, string $to): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $from) || Schema::hasColumn($table, $to)) {
            return;
        }

        $columnType = $this->mysqlColumnType($table, $from);
        if ($columnType === null) {
            throw new RuntimeException(sprintf(
                'UUID Phase 1 PR5A failed: unable to resolve type for %s.%s during rename.',
                $table,
                $from
            ));
        }

        DB::statement(sprintf(
            'ALTER TABLE `%s` CHANGE `%s` `%s` %s',
            $table,
            $from,
            $to,
            $columnType
        ));
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

    /**
     * @param array<int, string> $columns
     */
    private function dropCompositeIndex(string $table, array $columns): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        $needle = implode(',', $columns);

        $rows = DB::select(
            <<<'SQL'
            SELECT s.INDEX_NAME AS index_name,
                   GROUP_CONCAT(s.COLUMN_NAME ORDER BY s.SEQ_IN_INDEX SEPARATOR ',') AS column_list
            FROM information_schema.STATISTICS s
            WHERE s.TABLE_SCHEMA = DATABASE()
              AND s.TABLE_NAME = ?
              AND s.INDEX_NAME <> 'PRIMARY'
            GROUP BY s.INDEX_NAME
            SQL,
            [$table]
        );

        foreach ($rows as $row) {
            if ((string) $row->column_list !== $needle) {
                continue;
            }

            DB::statement(sprintf(
                'ALTER TABLE `%s` DROP INDEX `%s`',
                $table,
                (string) $row->index_name
            ));
        }
    }

    private function indexExistsByName(string $table, string $indexName): bool
    {
        if (!Schema::hasTable($table)) {
            return false;
        }

        $row = DB::selectOne(
            <<<'SQL'
            SELECT COUNT(*) AS aggregate_count
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND INDEX_NAME = ?
            SQL,
            [$table, $indexName]
        );

        return ((int) ($row->aggregate_count ?? 0)) > 0;
    }

    /**
     * @return array<int, string>
     */
    private function sampleDistinctValues(\Illuminate\Database\Query\Builder $query, string $column): array
    {
        /** @var array<int, scalar|null> $values */
        $values = $query
            ->selectRaw('DISTINCT '.$column.' AS sampled_value')
            ->limit(5)
            ->pluck('sampled_value')
            ->all();

        $samples = [];
        foreach ($values as $value) {
            if ($value === null) {
                $samples[] = 'NULL';
                continue;
            }

            $trimmed = trim((string) $value);
            $samples[] = $trimmed === '' ? '<blank>' : (string) $value;
        }

        $samples = array_values(array_unique($samples));

        if ($samples === []) {
            return ['none'];
        }

        return $samples;
    }
};
