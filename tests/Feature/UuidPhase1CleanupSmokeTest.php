<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UuidPhase1CleanupSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_cleanup_removes_legacy_columns_and_keeps_uuid_account_indexes_on_mysql(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('PR5B cleanup smoke is MySQL-only.');
        }

        $requiredTables = ['accounts', 'users', 'shipments', 'wallets'];
        foreach ($requiredTables as $table) {
            if (!Schema::hasTable($table)) {
                $this->markTestSkipped("Required table {$table} is not available in this environment.");
            }
        }

        $this->assertFalse(Schema::hasColumn('accounts', 'id_legacy'));
        $this->assertFalse(Schema::hasColumn('users', 'id_legacy'));
        $this->assertFalse(Schema::hasColumn('users', 'account_id_legacy'));
        $this->assertFalse(Schema::hasColumn('shipments', 'id_legacy'));
        $this->assertFalse(Schema::hasColumn('shipments', 'account_id_legacy'));
        $this->assertFalse(Schema::hasColumn('shipments', 'user_id_legacy'));
        $this->assertFalse(Schema::hasColumn('wallets', 'id_legacy'));
        $this->assertFalse(Schema::hasColumn('wallets', 'account_id_legacy'));

        $this->assertUuidIndexedAccountId('users');
        $this->assertUuidIndexedAccountId('shipments');
        $this->assertUuidIndexedAccountId('wallets');
    }

    private function assertUuidIndexedAccountId(string $table): void
    {
        $this->assertTrue(Schema::hasColumn($table, 'account_id'), "{$table}.account_id should exist.");

        $columnType = $this->mysqlColumnType($table, 'account_id');
        $this->assertNotNull($columnType);
        $normalizedType = strtolower((string) $columnType);
        $this->assertFalse($this->looksNumericType($normalizedType), "{$table}.account_id should be UUID-compatible, got {$normalizedType}");

        $indexCount = (int) DB::table('information_schema.STATISTICS')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', 'account_id')
            ->count();

        $this->assertGreaterThan(0, $indexCount, "{$table}.account_id should be indexed.");
    }

    private function mysqlColumnType(string $table, string $column): ?string
    {
        $row = DB::selectOne(
            <<<'SQL'
            SELECT COLUMN_TYPE AS column_type
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
            LIMIT 1
            SQL,
            [$table, $column]
        );

        if ($row === null || !isset($row->column_type)) {
            return null;
        }

        return (string) $row->column_type;
    }

    private function looksNumericType(string $columnType): bool
    {
        foreach (['int', 'decimal', 'double', 'float', 'numeric', 'real'] as $needle) {
            if (str_contains($columnType, $needle)) {
                return true;
            }
        }

        return false;
    }
}
