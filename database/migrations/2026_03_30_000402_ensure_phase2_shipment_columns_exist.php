<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shipments')) {
            return;
        }

        Schema::table('shipments', function (Blueprint $table): void {
            if (! Schema::hasColumn('shipments', 'shipment_type')) {
                $table->enum('shipment_type', ['air', 'sea', 'land', 'express', 'multimodal'])->default('express');
            }

            if (! Schema::hasColumn('shipments', 'service_level')) {
                $table->enum('service_level', ['express', 'standard', 'economy', 'premium', 'same_day'])->default('standard');
            }

            if (! Schema::hasColumn('shipments', 'incoterm_code')) {
                $table->string('incoterm_code', 3)->nullable();
            }

            if (! Schema::hasColumn('shipments', 'origin_branch_id')) {
                $table->uuid('origin_branch_id')->nullable();
            }

            if (! Schema::hasColumn('shipments', 'destination_branch_id')) {
                $table->uuid('destination_branch_id')->nullable();
            }

            if (! Schema::hasColumn('shipments', 'company_id')) {
                $table->uuid('company_id')->nullable();
            }

            if (! Schema::hasColumn('shipments', 'declared_value')) {
                $table->decimal('declared_value', 14, 2)->default(0);
            }

            if (! Schema::hasColumn('shipments', 'total_volume')) {
                $table->decimal('total_volume', 10, 4)->nullable();
            }

            if (! Schema::hasColumn('shipments', 'insurance_flag')) {
                $table->boolean('insurance_flag')->default(false);
            }

            if (! Schema::hasColumn('shipments', 'driver_id')) {
                $table->uuid('driver_id')->nullable();
            }

            if (! Schema::hasColumn('shipments', 'pod_status')) {
                $table->string('pod_status', 20)->nullable();
            }
        });
    }

    public function down(): void
    {
        // Forward-only safety: these columns may have originated from older migrations
        // on existing environments, so dropping them here would be unsafe.
    }
};
