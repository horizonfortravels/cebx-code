<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->targetNumericIdTables() as $table) {
            if (!Schema::hasTable($table) || Schema::hasColumn($table, 'id_uuid')) {
                continue;
            }

            $indexName = $this->shadowUuidIndexName($table);

            Schema::table($table, function (Blueprint $blueprint) use ($indexName): void {
                $blueprint->uuid('id_uuid')->nullable()->after('id');
                $blueprint->unique('id_uuid', $indexName);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->targetNumericIdTables() as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'id_uuid')) {
                continue;
            }

            $indexName = $this->shadowUuidIndexName($table);

            Schema::table($table, function (Blueprint $blueprint) use ($indexName): void {
                $blueprint->dropUnique($indexName);
                $blueprint->dropColumn('id_uuid');
            });
        }
    }

    /**
     * @return array<int, string>
     */
    private function targetNumericIdTables(): array
    {
        $tables = [];

        foreach (Schema::getTableListing() as $table) {
            if ($this->isIgnoredTable($table)) {
                continue;
            }

            if (!Schema::hasColumn($table, 'id')) {
                continue;
            }

            $idType = strtolower((string) Schema::getColumnType($table, 'id'));
            if (!$this->isNumericType($idType)) {
                continue;
            }

            $tables[] = $table;
        }

        sort($tables);

        return $tables;
    }

    private function isIgnoredTable(string $table): bool
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

    private function isNumericType(string $type): bool
    {
        return in_array($type, [
            'bigint',
            'bigint unsigned',
            'biginteger',
            'int',
            'integer',
            'integer unsigned',
            'smallint',
            'smallinteger',
            'tinyint',
            'tinyinteger',
            'mediumint',
            'mediuminteger',
            'numeric',
            'decimal',
            'float',
            'double',
            'real',
        ], true);
    }

    private function shadowUuidIndexName(string $table): string
    {
        return 'uq_'.$table.'_id_uuid_'.substr(md5($table), 0, 8);
    }
};
