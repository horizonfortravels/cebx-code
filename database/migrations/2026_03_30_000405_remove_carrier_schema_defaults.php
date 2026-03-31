<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('carrier_shipments')) {
            DB::statement("ALTER TABLE `carrier_shipments` MODIFY `carrier_code` VARCHAR(50) NOT NULL");
            DB::statement("ALTER TABLE `carrier_shipments` MODIFY `carrier_name` VARCHAR(100) NOT NULL");
        }

        if (Schema::hasTable('carrier_errors')) {
            DB::statement("ALTER TABLE `carrier_errors` MODIFY `carrier_code` VARCHAR(50) NOT NULL");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('carrier_shipments')) {
            DB::statement("ALTER TABLE `carrier_shipments` MODIFY `carrier_code` VARCHAR(50) NOT NULL DEFAULT 'dhl'");
            DB::statement("ALTER TABLE `carrier_shipments` MODIFY `carrier_name` VARCHAR(100) NOT NULL DEFAULT 'DHL Express'");
        }

        if (Schema::hasTable('carrier_errors')) {
            DB::statement("ALTER TABLE `carrier_errors` MODIFY `carrier_code` VARCHAR(50) NOT NULL DEFAULT 'dhl'");
        }
    }
};
