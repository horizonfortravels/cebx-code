<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dg_audit_logs')) {
            return;
        }

        if (! Schema::hasColumn('dg_audit_logs', 'shipment_uuid')) {
            Schema::table('dg_audit_logs', function (Blueprint $table): void {
                $table->uuid('shipment_uuid')->nullable()->after('shipment_id');
            });
        }

        $this->backfillShipmentUuid();
        $this->ensureShipmentUuidIndex();
    }

    public function down(): void
    {
        // Forward-only UUID normalization tranche.
    }

    private function backfillShipmentUuid(): void
    {
        if (
            ! Schema::hasTable('shipments')
            || ! Schema::hasColumn('shipments', 'id')
            || ! Schema::hasColumn('dg_audit_logs', 'shipment_id')
            || ! Schema::hasColumn('dg_audit_logs', 'shipment_uuid')
        ) {
            return;
        }

        DB::statement(
            <<<'SQL'
            UPDATE `dg_audit_logs` AS `logs`
            INNER JOIN `shipments` AS `shipments`
                ON TRIM(CAST(`logs`.`shipment_id` AS CHAR)) = CAST(`shipments`.`id` AS CHAR)
            SET `logs`.`shipment_uuid` = `shipments`.`id`
            WHERE `logs`.`shipment_id` IS NOT NULL
              AND TRIM(CAST(`logs`.`shipment_id` AS CHAR)) <> ''
              AND (`logs`.`shipment_uuid` IS NULL OR TRIM(CAST(`logs`.`shipment_uuid` AS CHAR)) = '')
            SQL
        );
    }

    private function ensureShipmentUuidIndex(): void
    {
        if (! Schema::hasColumn('dg_audit_logs', 'shipment_uuid')) {
            return;
        }

        $indexName = 'dg_audit_logs_shipment_uuid_created_idx';

        $result = DB::selectOne(
            <<<'SQL'
            SELECT COUNT(*) AS aggregate_count
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'dg_audit_logs'
              AND INDEX_NAME = ?
            SQL,
            [$indexName]
        );

        if (((int) ($result->aggregate_count ?? 0)) > 0) {
            return;
        }

        Schema::table('dg_audit_logs', function (Blueprint $table) use ($indexName): void {
            $table->index(['shipment_uuid', 'created_at'], $indexName);
        });
    }
};
