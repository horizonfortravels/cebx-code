<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('accounts') || !Schema::hasColumn('accounts', 'status')) {
            return;
        }

        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $column = DB::selectOne("SHOW COLUMNS FROM `accounts` LIKE 'status'");
            $type = strtolower((string) ($column->Type ?? ''));

            if (str_contains($type, "'closed'")) {
                return;
            }

            DB::statement(
                "ALTER TABLE `accounts` MODIFY `status` ENUM('active','pending','suspended','closed') NOT NULL DEFAULT 'pending'"
            );

            return;
        }

        if ($driver === 'pgsql') {
            $enumType = DB::table('information_schema.columns')
                ->where('table_schema', 'public')
                ->where('table_name', 'accounts')
                ->where('column_name', 'status')
                ->value('udt_name');

            if (is_string($enumType) && $enumType !== '') {
                DB::statement(sprintf('ALTER TYPE "%s" ADD VALUE IF NOT EXISTS \'closed\'', $enumType));
            }
        }
    }

    public function down(): void
    {
        // No down migration: shrinking enum values is unsafe on populated environments.
    }
};
