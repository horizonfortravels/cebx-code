<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * جدول shipments (من 2026_02_12_000013) يعرّف actual_delivery_at وليس delivered_at.
 * الكود (PageController، Shipment model، seeders، services) يستخدم delivered_at.
 * نضيف العمود ونملأه من actual_delivery_at للتوافق.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('shipments', 'delivered_at')) {
            return;
        }

        Schema::table('shipments', function (Blueprint $table) {
            $table->timestamp('delivered_at')->nullable()->after('actual_delivery_at')->comment('Alias / compat with actual_delivery_at');
        });

        if (Schema::hasColumn('shipments', 'actual_delivery_at')) {
            DB::table('shipments')->whereNotNull('actual_delivery_at')->update([
                'delivered_at' => DB::raw('actual_delivery_at'),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn('delivered_at');
        });
    }
};
