<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('audit_logs')) {
            $this->createCanonicalAuditLogsTable();

            return;
        }

        $this->addMissingCanonicalColumns();
        $this->backfillCanonicalColumns();
        $this->ensureCanonicalIndexes();
        $this->ensureCanonicalForeignKeys();
    }

    public function down(): void
    {
        // Forward-only compatibility migration.
    }

    private function createCanonicalAuditLogsTable(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('account_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->string('action', 100)->nullable();
            $table->string('severity', 20)->default('info');
            $table->string('category', 50)->nullable();
            $table->string('entity_type', 100)->nullable();
            $table->string('entity_id', 36)->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('request_id', 64)->nullable();
            $table->timestamps();
        });

        $this->ensureCanonicalIndexes();
        $this->ensureCanonicalForeignKeys();
    }

    private function addMissingCanonicalColumns(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            if (!Schema::hasColumn('audit_logs', 'account_id')) {
                $table->uuid('account_id')->nullable();
            }

            if (!Schema::hasColumn('audit_logs', 'action')) {
                $table->string('action', 100)->nullable();
            }

            if (!Schema::hasColumn('audit_logs', 'severity')) {
                $table->string('severity', 20)->default('info');
            }

            if (!Schema::hasColumn('audit_logs', 'category')) {
                $table->string('category', 50)->nullable();
            }

            if (!Schema::hasColumn('audit_logs', 'entity_type')) {
                $table->string('entity_type', 100)->nullable();
            }

            if (!Schema::hasColumn('audit_logs', 'entity_id')) {
                $table->string('entity_id', 36)->nullable();
            }

            if (!Schema::hasColumn('audit_logs', 'user_agent')) {
                $table->text('user_agent')->nullable();
            }

            if (!Schema::hasColumn('audit_logs', 'request_id')) {
                $table->string('request_id', 64)->nullable();
            }

            if (!Schema::hasColumn('audit_logs', 'metadata')) {
                $table->json('metadata')->nullable();
            }

            if (!Schema::hasColumn('audit_logs', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });
    }

    private function backfillCanonicalColumns(): void
    {
        if (Schema::hasColumn('audit_logs', 'action') && Schema::hasColumn('audit_logs', 'event')) {
            DB::statement("
                UPDATE `audit_logs`
                SET `action` = `event`
                WHERE `action` IS NULL AND `event` IS NOT NULL
            ");
        }

        if (Schema::hasColumn('audit_logs', 'entity_type') && Schema::hasColumn('audit_logs', 'auditable_type')) {
            DB::statement("
                UPDATE `audit_logs`
                SET `entity_type` = `auditable_type`
                WHERE `entity_type` IS NULL AND `auditable_type` IS NOT NULL
            ");
        }

        if (Schema::hasColumn('audit_logs', 'entity_id') && Schema::hasColumn('audit_logs', 'auditable_id')) {
            DB::statement("
                UPDATE `audit_logs`
                SET `entity_id` = CAST(`auditable_id` AS CHAR(36))
                WHERE `entity_id` IS NULL AND `auditable_id` IS NOT NULL
            ");
        }
    }

    private function ensureCanonicalIndexes(): void
    {
        $this->ensureIndex('audit_logs', 'idx_audit_logs_account_id', ['account_id']);
        $this->ensureIndex('audit_logs', 'idx_audit_logs_user_id', ['user_id']);
        $this->ensureIndex('audit_logs', 'idx_audit_logs_action', ['action']);
        $this->ensureIndex('audit_logs', 'idx_audit_logs_created_at', ['created_at']);
        $this->ensureIndex('audit_logs', 'idx_audit_logs_entity', ['entity_type', 'entity_id']);
        $this->ensureIndex('audit_logs', 'idx_audit_logs_request_id', ['request_id']);
        $this->ensureIndex('audit_logs', 'idx_audit_account_category_time', ['account_id', 'category', 'created_at']);
        $this->ensureIndex('audit_logs', 'idx_audit_account_severity_time', ['account_id', 'severity', 'created_at']);
        $this->ensureIndex('audit_logs', 'idx_audit_account_user_time', ['account_id', 'user_id', 'created_at']);
    }

    private function ensureCanonicalForeignKeys(): void
    {
        $this->ensureNullableUuidForeignKey('audit_logs', 'account_id', 'accounts');
        $this->ensureNullableUuidForeignKey('audit_logs', 'user_id', 'users');
    }

    /**
     * @param array<int, string> $columns
     */
    private function ensureIndex(string $table, string $indexName, array $columns): void
    {
        if (!Schema::hasTable($table) || $this->hasIndex($table, $indexName)) {
            return;
        }

        foreach ($columns as $column) {
            if (!Schema::hasColumn($table, $column)) {
                return;
            }
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $indexName): void {
            $blueprint->index($columns, $indexName);
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
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

    private function ensureNullableUuidForeignKey(string $table, string $column, string $parentTable): void
    {
        if (
            !Schema::hasTable($table) ||
            !Schema::hasTable($parentTable) ||
            !Schema::hasColumn($table, $column) ||
            !Schema::hasColumn($parentTable, 'id')
        ) {
            return;
        }

        if (!$this->isUuidLength36Column($table, $column) || !$this->isUuidLength36Column($parentTable, 'id')) {
            return;
        }

        $constraintName = sprintf('fk_%s_%s_%s', $table, $column, $parentTable);
        if ($this->hasForeignKey($table, $constraintName)) {
            return;
        }

        $this->dropForeignKeysOnColumn($table, $column);

        DB::statement(sprintf(
            'ALTER TABLE `%s` ADD CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES `%s`(`id`) ON DELETE SET NULL',
            $table,
            $constraintName,
            $column,
            $parentTable
        ));
    }

    private function hasForeignKey(string $table, string $constraintName): bool
    {
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

    private function isUuidLength36Column(string $table, string $column): bool
    {
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
            return false;
        }

        $type = strtolower((string) $row->COLUMN_TYPE);

        return str_contains($type, 'char(36)') || str_contains($type, 'varchar(36)');
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
