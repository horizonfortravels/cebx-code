<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        Schema::table('notifications', function (Blueprint $table): void {
            if (! Schema::hasColumn('notifications', 'shipment_event_id')) {
                $table->uuid('shipment_event_id')->nullable()->after('entity_id');
            }

            $table->unique(
                ['shipment_event_id', 'user_id', 'channel'],
                'notifications_shipment_event_user_channel_unique'
            );
        });
    }

    public function down(): void
    {
        // Forward-only hardening migration.
    }
};
