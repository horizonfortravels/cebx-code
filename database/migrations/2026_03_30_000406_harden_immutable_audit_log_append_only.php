<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('immutable_audit_log')) {
            return;
        }

        if (Schema::hasColumn('immutable_audit_log', 'updated_at')) {
            DB::statement('ALTER TABLE `immutable_audit_log` DROP COLUMN `updated_at`');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('immutable_audit_log')) {
            return;
        }

        if (! Schema::hasColumn('immutable_audit_log', 'updated_at')) {
            DB::statement('ALTER TABLE `immutable_audit_log` ADD COLUMN `updated_at` TIMESTAMP NULL AFTER `created_at`');
        }
    }
};
