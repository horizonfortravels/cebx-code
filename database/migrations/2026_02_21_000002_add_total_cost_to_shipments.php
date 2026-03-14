<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * جدول shipments (من 2026_02_12_000013) يعرّف total_charge وليس total_cost.
 * الكود (PageController، ShipmentWebController، views) يستخدم total_cost.
 * نضيف العمود ونملأه من total_charge للتوافق.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('shipments', 'total_cost')) {
            return;
        }

        Schema::table('shipments', function (Blueprint $table) {
            $table->decimal('total_cost', 15, 2)->nullable()->after('total_charge')->comment('Alias / compat with total_charge');
        });

        DB::table('shipments')->whereNotNull('total_charge')->update([
            'total_cost' => DB::raw('total_charge'),
        ]);
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn('total_cost');
        });
    }
};
