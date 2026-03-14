<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SH Module — Shipments Management
 *
 * FR-SH-001→019: Full shipment lifecycle
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('addresses')) {
            return;
        }

        // ── Address Book (FR-SH-004) ─────────────────────────────
        Schema::create('addresses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('account_id');
            $table->enum('type', ['sender', 'recipient', 'both'])->default('both');
            $table->boolean('is_default_sender')->default(false);

            $table->string('label', 100)->nullable()->comment('e.g. "Main Warehouse", "Home"');
            $table->string('contact_name', 200);
            $table->string('company_name', 200)->nullable();
            $table->string('phone', 30);
            $table->string('email', 255)->nullable();
            $table->string('address_line_1', 300);
            $table->string('address_line_2', 300)->nullable();
            $table->string('city', 100);
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 2);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['account_id', 'type']);
            // FK omitted: accounts.id may be bigint on server
        });

        // ── Shipments (FR-SH-001/002/006) ────────────────────────
        if (! Schema::hasTable('shipments')) {
        Schema::create('shipments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('account_id');
            $table->uuid('store_id')->nullable();
            $table->uuid('order_id')->nullable()->comment('Linked canonical order');
            $table->string('reference_number', 50)->unique()->comment('Internal SHP-XXXXXXXX');

            // Source
            $table->enum('source', ['direct', 'order', 'bulk', 'return'])->default('direct');

            // Status — FR-SH-006 State Machine
            $table->enum('status', [
                'draft',             // Initial creation
                'validated',         // Passed validation (FR-SH-005)
                'rated',             // Rates fetched
                'payment_pending',   // Awaiting balance/payment
                'purchased',         // Label purchased / created
                'ready_for_pickup',  // Label printed, awaiting carrier
                'picked_up',         // Carrier collected
                'in_transit',        // En route
                'out_for_delivery',  // Last mile
                'delivered',         // Successfully delivered
                'returned',          // Returned to sender
                'exception',         // Carrier exception
                'cancelled',         // Cancelled/voided
                'failed',            // Creation/label failed
            ])->default('draft');
            $table->string('status_reason', 500)->nullable();

            // Carrier & Service
            $table->string('carrier_code', 50)->nullable()->comment('e.g. dhl_express, aramex');
            $table->string('carrier_name', 100)->nullable();
            $table->string('service_code', 50)->nullable()->comment('e.g. express, economy');
            $table->string('service_name', 100)->nullable();

            // Tracking
            $table->string('tracking_number', 100)->nullable();
            $table->string('carrier_shipment_id', 200)->nullable()->comment('Carrier internal ID');
            $table->string('tracking_url', 500)->nullable();

            // Sender Address
            $table->uuid('sender_address_id')->nullable();
            $table->string('sender_name', 200);
            $table->string('sender_company', 200)->nullable();
            $table->string('sender_phone', 30);
            $table->string('sender_email', 255)->nullable();
            $table->string('sender_address_1', 300);
            $table->string('sender_address_2', 300)->nullable();
            $table->string('sender_city', 100);
            $table->string('sender_state', 100)->nullable();
            $table->string('sender_postal_code', 20)->nullable();
            $table->string('sender_country', 2);

            // Recipient Address
            $table->uuid('recipient_address_id')->nullable();
            $table->string('recipient_name', 200);
            $table->string('recipient_company', 200)->nullable();
            $table->string('recipient_phone', 30);
            $table->string('recipient_email', 255)->nullable();
            $table->string('recipient_address_1', 300);
            $table->string('recipient_address_2', 300)->nullable();
            $table->string('recipient_city', 100);
            $table->string('recipient_state', 100)->nullable();
            $table->string('recipient_postal_code', 20)->nullable();
            $table->string('recipient_country', 2);

            // Financial — FR-SH-011 (visibility controlled by RBAC)
            $table->decimal('shipping_rate', 15, 2)->nullable()->comment('Rate from carrier');
            $table->decimal('insurance_amount', 15, 2)->default(0);
            $table->decimal('cod_amount', 15, 2)->default(0)->comment('FR-SH-019 COD');
            $table->decimal('total_charge', 15, 2)->nullable()->comment('Final charge to wallet');
            $table->decimal('platform_fee', 15, 2)->default(0);
            $table->decimal('profit_margin', 15, 2)->default(0)->comment('Hidden from warehouse roles');
            $table->string('currency', 3)->default('SAR');

            // Weight & Dimensions (aggregated from parcels)
            $table->decimal('total_weight', 10, 3)->nullable()->comment('Actual weight kg');
            $table->decimal('volumetric_weight', 10, 3)->nullable();
            $table->decimal('chargeable_weight', 10, 3)->nullable();
            $table->integer('parcels_count')->default(1);

            // Flags
            $table->boolean('is_international')->default(false);
            $table->boolean('is_cod')->default(false);
            $table->boolean('is_insured')->default(false);
            $table->boolean('is_return')->default(false)->comment('FR-SH-016');
            $table->boolean('has_dangerous_goods')->default(false)->comment('FR-SH-017');
            $table->string('dg_declaration_status', 30)->nullable()->comment('pending/approved/rejected');
            $table->boolean('kyc_verified')->default(false)->comment('FR-SH-013');

            // Label — FR-SH-008
            $table->string('label_url', 500)->nullable();
            $table->string('label_format', 10)->nullable()->comment('pdf, zpl, png');
            $table->integer('label_print_count')->default(0);
            $table->timestamp('label_created_at')->nullable();

            // Balance reservation — FR-SH-014
            $table->uuid('balance_reservation_id')->nullable();
            $table->decimal('reserved_amount', 15, 2)->nullable();

            // Delivery info
            $table->text('delivery_instructions')->nullable();
            $table->timestamp('estimated_delivery_at')->nullable();
            $table->timestamp('actual_delivery_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();

            // Users
            $table->uuid('created_by');
            $table->uuid('cancelled_by')->nullable();
            $table->string('cancellation_reason', 500)->nullable();

            // Wallet ledger references
            $table->uuid('debit_ledger_entry_id')->nullable()->comment('FR-SH-015');
            $table->uuid('refund_ledger_entry_id')->nullable();

            // Rules evaluation (from ST-008)
            $table->json('rule_evaluation_log')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['account_id', 'status']);
            $table->index(['account_id', 'store_id']);
            $table->index(['account_id', 'created_at']);
            $table->index('tracking_number');
            $table->index('order_id');
            $table->index('store_id');
            $table->index('created_by');
            $table->index('sender_address_id');
            $table->index('recipient_address_id');
            $table->index('cancelled_by');
            // FKs omitted: accounts, stores, orders, users, addresses may have bigint id on server
        });
        }

        // ── Parcels / Multi-parcel (FR-SH-003) ──────────────────
        if (! Schema::hasTable('parcels')) {
        Schema::create('parcels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('shipment_id');
            $table->integer('sequence')->default(1);

            $table->decimal('weight', 10, 3)->comment('kg');
            $table->decimal('length', 10, 2)->nullable()->comment('cm');
            $table->decimal('width', 10, 2)->nullable()->comment('cm');
            $table->decimal('height', 10, 2)->nullable()->comment('cm');
            $table->decimal('volumetric_weight', 10, 3)->nullable();

            $table->string('packaging_type', 50)->default('custom')->comment('box, envelope, tube, custom');
            $table->string('description', 300)->nullable();
            $table->string('reference', 100)->nullable();

            // Carrier-specific
            $table->string('carrier_parcel_id', 200)->nullable();
            $table->string('carrier_tracking', 100)->nullable()->comment('Per-parcel tracking if different');
            $table->string('label_url', 500)->nullable();

            $table->timestamps();

            $table->index('shipment_id');
            // FK omitted for server compatibility
        });
        }

        // ── Shipment Status History (FR-SH-006) ──────────────────
        if (! Schema::hasTable('shipment_status_history')) {
        Schema::create('shipment_status_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('shipment_id');
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->string('source', 30)->default('system')->comment('system, carrier, user');
            $table->string('reason', 500)->nullable();
            $table->uuid('changed_by')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');

            $table->index(['shipment_id', 'created_at']);
            $table->index('changed_by');
            // FK omitted for server compatibility
        });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_status_history');
        Schema::dropIfExists('parcels');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('addresses');
    }
};
