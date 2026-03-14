<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wallet_holds')) {
            return;
        }

        Schema::table('wallet_holds', function (Blueprint $table): void {
            if (! Schema::hasColumn('wallet_holds', 'account_id')) {
                $table->uuid('account_id')->nullable()->after('wallet_id');
            }

            if (! Schema::hasColumn('wallet_holds', 'currency')) {
                $table->string('currency', 3)->nullable()->after('amount');
            }

            if (! Schema::hasColumn('wallet_holds', 'source')) {
                $table->string('source', 64)->nullable()->after('shipment_id');
            }

            if (! Schema::hasColumn('wallet_holds', 'correlation_id')) {
                $table->string('correlation_id', 200)->nullable()->after('idempotency_key');
            }

            if (! Schema::hasColumn('wallet_holds', 'actor_id')) {
                $table->uuid('actor_id')->nullable()->after('correlation_id');
            }
        });

        Schema::table('wallet_holds', function (Blueprint $table): void {
            if (! Schema::hasColumn('wallet_holds', 'account_id')) {
                return;
            }

            $table->index(['account_id', 'status'], 'wallet_holds_account_status_idx');
            $table->index(['shipment_id', 'status'], 'wallet_holds_shipment_status_idx');

            if (Schema::hasColumn('wallet_holds', 'source')) {
                $table->index(['source', 'status'], 'wallet_holds_source_status_idx');
            }

            if (Schema::hasColumn('wallet_holds', 'correlation_id')) {
                $table->index('correlation_id', 'wallet_holds_correlation_idx');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('wallet_holds')) {
            return;
        }

        Schema::table('wallet_holds', function (Blueprint $table): void {
            foreach ([
                'wallet_holds_account_status_idx',
                'wallet_holds_shipment_status_idx',
                'wallet_holds_source_status_idx',
                'wallet_holds_correlation_idx',
            ] as $indexName) {
                try {
                    $table->dropIndex($indexName);
                } catch (\Throwable) {
                    // Index may not exist in some environments.
                }
            }

            foreach (['account_id', 'currency', 'source', 'correlation_id', 'actor_id'] as $column) {
                if (Schema::hasColumn('wallet_holds', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
