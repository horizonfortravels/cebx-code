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
            if (! Schema::hasColumn('shipments', 'public_tracking_token')) {
                $table->text('public_tracking_token')->nullable()->after('tracking_url');
            }

            if (! Schema::hasColumn('shipments', 'public_tracking_token_hash')) {
                $table->string('public_tracking_token_hash', 64)->nullable()->after('public_tracking_token');
                $table->unique('public_tracking_token_hash', 'shipments_public_tracking_token_hash_unique');
            }

            if (! Schema::hasColumn('shipments', 'public_tracking_enabled_at')) {
                $table->timestamp('public_tracking_enabled_at')->nullable()->after('public_tracking_token_hash');
            }

            if (! Schema::hasColumn('shipments', 'public_tracking_expires_at')) {
                $table->timestamp('public_tracking_expires_at')->nullable()->after('public_tracking_enabled_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('shipments')) {
            return;
        }

        Schema::table('shipments', function (Blueprint $table): void {
            if (Schema::hasColumn('shipments', 'public_tracking_token_hash')) {
                $table->dropUnique('shipments_public_tracking_token_hash_unique');
            }

            foreach ([
                'public_tracking_expires_at',
                'public_tracking_enabled_at',
                'public_tracking_token_hash',
                'public_tracking_token',
            ] as $column) {
                if (Schema::hasColumn('shipments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
