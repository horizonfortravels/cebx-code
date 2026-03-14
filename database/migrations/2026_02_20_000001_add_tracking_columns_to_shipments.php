<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FIX P0-4: إضافة أعمدة التتبع المفقودة في جدول shipments
 *
 * TrackingService يحدّث هذه الأعمدة لكنها غير موجودة في migration الأصلي.
 * - tracking_status: حالة التتبع الحالية (من الناقل)
 * - tracking_updated_at: آخر تحديث للتتبع
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            // FIX P0-4: أعمدة التتبع التي يعتمد عليها TrackingService (no ->after() for compatibility with differing server schema)
            if (! Schema::hasColumn('shipments', 'tracking_status')) {
                $table->string('tracking_status', 50)
                      ->nullable()
                      ->index()
                      ->comment('Carrier tracking status (e.g. in_transit, delivered)');
            }

            if (! Schema::hasColumn('shipments', 'tracking_updated_at')) {
                $table->timestamp('tracking_updated_at')
                      ->nullable()
                      ->index()
                      ->comment('Last tracking event timestamp');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn(['tracking_status', 'tracking_updated_at']);
        });
    }
};
