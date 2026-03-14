<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('accounts') || !Schema::hasColumn('accounts', 'type')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver !== 'mysql') {
            return;
        }

        $columnType = $this->mysqlColumnType('accounts', 'type');
        if ($columnType === null) {
            return;
        }

        if (!str_starts_with(strtolower($columnType), 'enum(')) {
            return;
        }

        DB::statement(
            "ALTER TABLE `accounts` MODIFY `type` ENUM('individual','organization') NOT NULL DEFAULT 'individual'"
        );
    }

    public function down(): void
    {
        if (!Schema::hasTable('accounts') || !Schema::hasColumn('accounts', 'type')) {
            return;
        }

        $driver = DB::getDriverName();
        if ($driver !== 'mysql') {
            return;
        }

        $columnType = $this->mysqlColumnType('accounts', 'type');
        if ($columnType === null || !str_starts_with(strtolower($columnType), 'enum(')) {
            return;
        }

        DB::statement(
            "ALTER TABLE `accounts` MODIFY `type` ENUM('individual','organization','business','admin') NOT NULL DEFAULT 'organization'"
        );
    }

    private function mysqlColumnType(string $table, string $column): ?string
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
            return null;
        }

        return (string) $row->COLUMN_TYPE;
    }
};
