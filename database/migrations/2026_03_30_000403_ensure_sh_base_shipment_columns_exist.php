<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shipments')) {
            return;
        }

        Schema::table('shipments', function (Blueprint $table): void {
            if (! Schema::hasColumn('shipments', 'store_id')) {
                $table->uuid('store_id')->nullable()->after('account_id');
            }

            if (! Schema::hasColumn('shipments', 'status_reason')) {
                $table->string('status_reason', 500)->nullable()->after('status');
            }

            if (! Schema::hasColumn('shipments', 'service_code')) {
                $table->string('service_code', 50)->nullable()->after('carrier_name');
            }

            if (! Schema::hasColumn('shipments', 'service_name')) {
                $table->string('service_name', 100)->nullable()->after('service_code');
            }

            if (! Schema::hasColumn('shipments', 'tracking_number')) {
                $table->string('tracking_number', 100)->nullable()->after('service_name');
            }

            if (! Schema::hasColumn('shipments', 'carrier_shipment_id')) {
                $table->string('carrier_shipment_id', 200)->nullable()->after('tracking_number');
            }

            if (! Schema::hasColumn('shipments', 'tracking_url')) {
                $table->string('tracking_url', 500)->nullable()->after('carrier_shipment_id');
            }

            if (! Schema::hasColumn('shipments', 'sender_address_id')) {
                $table->uuid('sender_address_id')->nullable()->after('tracking_url');
            }

            if (! Schema::hasColumn('shipments', 'sender_company')) {
                $table->string('sender_company', 200)->nullable()->after('sender_name');
            }

            if (! Schema::hasColumn('shipments', 'sender_email')) {
                $table->string('sender_email', 255)->nullable()->after('sender_phone');
            }

            if (! Schema::hasColumn('shipments', 'sender_address_1')) {
                $table->string('sender_address_1', 300)->nullable()->after('sender_email');
            }

            if (! Schema::hasColumn('shipments', 'sender_address_2')) {
                $table->string('sender_address_2', 300)->nullable()->after('sender_address_1');
            }

            if (! Schema::hasColumn('shipments', 'sender_state')) {
                $table->string('sender_state', 100)->nullable()->after('sender_city');
            }

            if (! Schema::hasColumn('shipments', 'sender_postal_code')) {
                $table->string('sender_postal_code', 20)->nullable()->after('sender_state');
            }

            if (! Schema::hasColumn('shipments', 'sender_country')) {
                $table->string('sender_country', 2)->nullable()->after('sender_postal_code');
            }

            if (! Schema::hasColumn('shipments', 'recipient_address_id')) {
                $table->uuid('recipient_address_id')->nullable()->after('sender_country');
            }

            if (! Schema::hasColumn('shipments', 'recipient_company')) {
                $table->string('recipient_company', 200)->nullable()->after('recipient_name');
            }

            if (! Schema::hasColumn('shipments', 'recipient_email')) {
                $table->string('recipient_email', 255)->nullable()->after('recipient_phone');
            }

            if (! Schema::hasColumn('shipments', 'recipient_address_1')) {
                $table->string('recipient_address_1', 300)->nullable()->after('recipient_email');
            }

            if (! Schema::hasColumn('shipments', 'recipient_address_2')) {
                $table->string('recipient_address_2', 300)->nullable()->after('recipient_address_1');
            }

            if (! Schema::hasColumn('shipments', 'recipient_state')) {
                $table->string('recipient_state', 100)->nullable()->after('recipient_city');
            }

            if (! Schema::hasColumn('shipments', 'shipping_rate')) {
                $table->decimal('shipping_rate', 15, 2)->nullable()->after('cod_amount');
            }

            if (! Schema::hasColumn('shipments', 'insurance_amount')) {
                $table->decimal('insurance_amount', 15, 2)->default(0)->after('shipping_rate');
            }

            if (! Schema::hasColumn('shipments', 'total_charge')) {
                $table->decimal('total_charge', 15, 2)->nullable()->after('insurance_amount');
            }

            if (! Schema::hasColumn('shipments', 'platform_fee')) {
                $table->decimal('platform_fee', 15, 2)->default(0)->after('total_charge');
            }

            if (! Schema::hasColumn('shipments', 'profit_margin')) {
                $table->decimal('profit_margin', 15, 2)->default(0)->after('platform_fee');
            }

            if (! Schema::hasColumn('shipments', 'currency')) {
                $table->string('currency', 3)->default('SAR')->after('profit_margin');
            }

            if (! Schema::hasColumn('shipments', 'total_weight')) {
                $table->decimal('total_weight', 10, 3)->nullable()->after('currency');
            }

            if (! Schema::hasColumn('shipments', 'volumetric_weight')) {
                $table->decimal('volumetric_weight', 10, 3)->nullable()->after('total_weight');
            }

            if (! Schema::hasColumn('shipments', 'chargeable_weight')) {
                $table->decimal('chargeable_weight', 10, 3)->nullable()->after('volumetric_weight');
            }

            if (! Schema::hasColumn('shipments', 'parcels_count')) {
                $table->integer('parcels_count')->default(1)->after('chargeable_weight');
            }

            if (! Schema::hasColumn('shipments', 'is_international')) {
                $table->boolean('is_international')->default(false)->after('parcels_count');
            }

            if (! Schema::hasColumn('shipments', 'is_insured')) {
                $table->boolean('is_insured')->default(false)->after('is_cod');
            }

            if (! Schema::hasColumn('shipments', 'is_return')) {
                $table->boolean('is_return')->default(false)->after('is_insured');
            }

            if (! Schema::hasColumn('shipments', 'has_dangerous_goods')) {
                $table->boolean('has_dangerous_goods')->default(false)->after('is_return');
            }

            if (! Schema::hasColumn('shipments', 'dg_declaration_status')) {
                $table->string('dg_declaration_status', 30)->nullable()->after('has_dangerous_goods');
            }

            if (! Schema::hasColumn('shipments', 'kyc_verified')) {
                $table->boolean('kyc_verified')->default(false)->after('dg_declaration_status');
            }

            if (! Schema::hasColumn('shipments', 'label_format')) {
                $table->string('label_format', 10)->nullable()->after('label_url');
            }

            if (! Schema::hasColumn('shipments', 'label_print_count')) {
                $table->integer('label_print_count')->default(0)->after('label_format');
            }

            if (! Schema::hasColumn('shipments', 'label_created_at')) {
                $table->timestamp('label_created_at')->nullable()->after('label_print_count');
            }

            if (! Schema::hasColumn('shipments', 'balance_reservation_id')) {
                $table->uuid('balance_reservation_id')->nullable()->after('label_created_at');
            }

            if (! Schema::hasColumn('shipments', 'reserved_amount')) {
                $table->decimal('reserved_amount', 15, 2)->nullable()->after('balance_reservation_id');
            }

            if (! Schema::hasColumn('shipments', 'delivery_instructions')) {
                $table->text('delivery_instructions')->nullable()->after('reserved_amount');
            }

            if (! Schema::hasColumn('shipments', 'estimated_delivery_at')) {
                $table->timestamp('estimated_delivery_at')->nullable()->after('delivery_instructions');
            }

            if (! Schema::hasColumn('shipments', 'actual_delivery_at')) {
                $table->timestamp('actual_delivery_at')->nullable()->after('estimated_delivery_at');
            }

            if (! Schema::hasColumn('shipments', 'picked_up_at')) {
                $table->timestamp('picked_up_at')->nullable()->after('actual_delivery_at');
            }

            if (! Schema::hasColumn('shipments', 'created_by')) {
                $table->uuid('created_by')->nullable()->after('user_id');
            }

            if (! Schema::hasColumn('shipments', 'cancelled_by')) {
                $table->uuid('cancelled_by')->nullable()->after('created_by');
            }

            if (! Schema::hasColumn('shipments', 'cancellation_reason')) {
                $table->string('cancellation_reason', 500)->nullable()->after('cancelled_by');
            }

            if (! Schema::hasColumn('shipments', 'debit_ledger_entry_id')) {
                $table->uuid('debit_ledger_entry_id')->nullable()->after('cancellation_reason');
            }

            if (! Schema::hasColumn('shipments', 'refund_ledger_entry_id')) {
                $table->uuid('refund_ledger_entry_id')->nullable()->after('debit_ledger_entry_id');
            }

            if (! Schema::hasColumn('shipments', 'rule_evaluation_log')) {
                $table->json('rule_evaluation_log')->nullable()->after('refund_ledger_entry_id');
            }

            if (! Schema::hasColumn('shipments', 'metadata')) {
                $table->json('metadata')->nullable()->after('rule_evaluation_log');
            }

            if (! Schema::hasColumn('shipments', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        $this->ensureIndex('shipments', ['account_id', 'status'], 'shipments_account_id_status_index');
        $this->ensureIndex('shipments', ['account_id', 'store_id'], 'shipments_account_id_store_id_index');
        $this->ensureIndex('shipments', ['account_id', 'created_at'], 'shipments_account_id_created_at_index');
        $this->ensureIndex('shipments', ['has_dangerous_goods'], 'shipments_has_dangerous_goods_index');
        $this->ensureIndex('shipments', ['tracking_number'], 'shipments_tracking_number_index');
        $this->ensureIndex('shipments', ['order_id'], 'shipments_order_id_index');
        $this->ensureIndex('shipments', ['store_id'], 'shipments_store_id_index');
        $this->ensureIndex('shipments', ['created_by'], 'shipments_created_by_index');
        $this->ensureIndex('shipments', ['sender_address_id'], 'shipments_sender_address_id_index');
        $this->ensureIndex('shipments', ['recipient_address_id'], 'shipments_recipient_address_id_index');
        $this->ensureIndex('shipments', ['balance_reservation_id'], 'shipments_balance_reservation_idx');
        $this->ensureIndex('shipments', ['cancelled_by'], 'shipments_cancelled_by_index');
    }

    public function down(): void
    {
        // Forward-only safety: this migration may converge a legacy shipments table
        // that pre-dates it, so ownership-blind rollback is unsafe.
    }

    private function ensureIndex(string $table, array $columns, string $index): void
    {
        if (! Schema::hasTable($table) || $this->hasIndex($table, $index)) {
            return;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return;
            }
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $index): void {
            $blueprint->index($columns, $index);
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
