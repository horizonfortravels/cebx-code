<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shipment_events')) {
            return;
        }

        Schema::table('shipment_events', function (Blueprint $table): void {
            if (! Schema::hasColumn('shipment_events', 'event_type')) {
                $table->string('event_type', 100)->nullable()->after('status');
            }

            if (! Schema::hasColumn('shipment_events', 'payload')) {
                $table->json('payload')->nullable()->after('location');
            }

            if (! Schema::hasColumn('shipment_events', 'correlation_id')) {
                $table->string('correlation_id', 100)->nullable()->after('payload');
            }

            if (! Schema::hasColumn('shipment_events', 'idempotency_key')) {
                $table->string('idempotency_key', 200)->nullable()->after('correlation_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('shipment_events')) {
            return;
        }

        Schema::table('shipment_events', function (Blueprint $table): void {
            if (Schema::hasColumn('shipment_events', 'idempotency_key')) {
                $table->dropColumn('idempotency_key');
            }

            if (Schema::hasColumn('shipment_events', 'correlation_id')) {
                $table->dropColumn('correlation_id');
            }

            if (Schema::hasColumn('shipment_events', 'payload')) {
                $table->dropColumn('payload');
            }

            if (Schema::hasColumn('shipment_events', 'event_type')) {
                $table->dropColumn('event_type');
            }
        });
    }
};
