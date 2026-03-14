<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->targetNumericIdTables() as $table) {
            if (
                !Schema::hasTable($table) ||
                !Schema::hasColumn($table, 'id') ||
                !Schema::hasColumn($table, 'id_uuid')
            ) {
                continue;
            }

            DB::table($table)
                ->whereNull('id_uuid')
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($table): void {
                    foreach ($rows as $row) {
                        DB::table($table)
                            ->where('id', $row->id)
                            ->update(['id_uuid' => (string) Str::uuid()]);
                    }
                }, 'id');

            $nullCount = DB::table($table)->whereNull('id_uuid')->count();
            if ($nullCount > 0) {
                throw new RuntimeException("UUID shadow backfill failed: {$table}.id_uuid has {$nullCount} null value(s).");
            }

            $duplicateSamples = DB::table($table)
                ->select('id_uuid')
                ->whereNotNull('id_uuid')
                ->groupBy('id_uuid')
                ->havingRaw('COUNT(*) > 1')
                ->limit(5)
                ->pluck('id_uuid')
                ->map(static fn ($value): string => (string) $value)
                ->all();

            if ($duplicateSamples !== []) {
                throw new RuntimeException(
                    'UUID shadow backfill failed: duplicate values found in '
                    .$table
                    .'.id_uuid. Samples: '
                    .implode(', ', $duplicateSamples)
                );
            }
        }
    }

    public function down(): void
    {
        // No-op: this migration only backfills and validates shadow UUID values.
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
};
