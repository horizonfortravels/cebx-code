<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shipments') || Schema::hasColumn('shipments', 'has_dangerous_goods')) {
            return;
        }

        Schema::table('shipments', function (Blueprint $table) {
            $table->boolean('has_dangerous_goods')->default(false)->after('status_reason');
            $table->index('has_dangerous_goods');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('shipments') || ! Schema::hasColumn('shipments', 'has_dangerous_goods')) {
            return;
        }

        Schema::table('shipments', function (Blueprint $table) {
            $table->dropIndex(['has_dangerous_goods']);
            $table->dropColumn('has_dangerous_goods');
        });
    }
};
