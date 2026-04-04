<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('support_tickets')) {
            return;
        }

        if (Schema::hasColumn('support_tickets', 'assigned_to') && Schema::getColumnType('support_tickets', 'assigned_to') === 'bigint') {
            DB::statement('ALTER TABLE `support_tickets` MODIFY `assigned_to` CHAR(36) NULL');
        }

        if (Schema::hasColumn('support_tickets', 'shipment_id') && Schema::getColumnType('support_tickets', 'shipment_id') === 'bigint') {
            DB::statement('ALTER TABLE `support_tickets` MODIFY `shipment_id` CHAR(36) NULL');
        }
    }

    public function down(): void
    {
        // Forward-only alignment migration.
    }
};
