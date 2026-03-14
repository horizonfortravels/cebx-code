<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            if (! Schema::hasColumn('shipments', 'balance_reservation_id')) {
                $table->uuid('balance_reservation_id')
                    ->nullable();
                $table->index('balance_reservation_id', 'shipments_balance_reservation_idx');
            }

            if (! Schema::hasColumn('shipments', 'reserved_amount')) {
                $table->decimal('reserved_amount', 15, 2)
                    ->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            if (Schema::hasColumn('shipments', 'reserved_amount')) {
                $table->dropColumn('reserved_amount');
            }

            if (Schema::hasColumn('shipments', 'balance_reservation_id')) {
                $table->dropIndex('shipments_balance_reservation_idx');
                $table->dropColumn('balance_reservation_id');
            }
        });
    }
};
