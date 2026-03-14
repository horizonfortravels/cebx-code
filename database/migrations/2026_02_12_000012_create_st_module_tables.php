<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ST Module — Store Integrations & Order Sync
 *
 * FR-ST-001: Store Connection (extends stores table)
 * FR-ST-002: Webhook events tracking
 * FR-ST-004: Canonical Order model
 * FR-ST-005: Deduplication via external IDs
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders')) {
            return;
        }

        // ── Canonical Orders (FR-ST-004) ─────────────────────────
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('account_id');
            $table->uuid('store_id');

            // External reference (FR-ST-005: dedup key)
            $table->string('external_order_id', 200);
            $table->string('external_order_number', 100)->nullable()->comment('Human-readable order # from store');
            $table->string('source', 30)->default('manual')->comment('manual, shopify, woocommerce, salla, zid, custom_api');

            // Status
            $table->enum('status', [
                'pending',        // Imported, awaiting processing
                'ready',          // Validated, ready for shipment
                'processing',     // Shipment being created
                'shipped',        // Shipment created & label issued
                'delivered',      // Delivered to recipient
                'cancelled',      // Order cancelled
                'on_hold',        // Requires action (rules engine)
                'failed',         // Import/processing failed
            ])->default('pending');

            // Customer info
            $table->string('customer_name', 200)->nullable();
            $table->string('customer_email', 255)->nullable();
            $table->string('customer_phone', 30)->nullable();

            // Shipping address
            $table->string('shipping_name', 200)->nullable();
            $table->string('shipping_phone', 30)->nullable();
            $table->string('shipping_address_line_1', 300)->nullable();
            $table->string('shipping_address_line_2', 300)->nullable();
            $table->string('shipping_city', 100)->nullable();
            $table->string('shipping_state', 100)->nullable();
            $table->string('shipping_postal_code', 20)->nullable();
            $table->string('shipping_country', 2)->nullable();

            // Financial
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('shipping_cost', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('currency', 3)->default('SAR');

            // Weight & dimensions (aggregated)
            $table->decimal('total_weight', 10, 3)->nullable()->comment('kg');
            $table->integer('items_count')->default(0);

            // Shipment linkage
            $table->uuid('shipment_id')->nullable()->comment('Linked shipment after conversion');

            // Flags
            $table->boolean('auto_ship_eligible')->default(false);
            $table->string('hold_reason', 500)->nullable();
            $table->json('rule_evaluation_log')->nullable()->comment('FR-ST-008 smart rules');

            // Raw data
            $table->json('raw_payload')->nullable()->comment('Original payload from store');
            $table->json('metadata')->nullable();

            // Sync tracking
            $table->timestamp('external_created_at')->nullable();
            $table->timestamp('external_updated_at')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->uuid('imported_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->unique(['account_id', 'store_id', 'external_order_id'], 'orders_dedup_unique');
            $table->index(['account_id', 'status']);
            $table->index(['account_id', 'store_id', 'status']);
            $table->index(['account_id', 'created_at']);
            $table->index('shipment_id');
            $table->index('imported_by');
            // FKs omitted: accounts.id, stores.id, users.id may be bigint on server
        });

        // ── Order Items ──────────────────────────────────────────
        if (! Schema::hasTable('order_items')) {
        Schema::create('order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id');

            $table->string('external_item_id', 200)->nullable();
            $table->string('sku', 100)->nullable();
            $table->string('name', 300);
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('total_price', 15, 2)->default(0);
            $table->decimal('weight', 10, 3)->nullable()->comment('kg per unit');
            $table->string('hs_code', 20)->nullable()->comment('Harmonized System code for customs');
            $table->string('country_of_origin', 2)->nullable();
            $table->json('properties')->nullable()->comment('Variant properties: color, size, etc.');
            $table->timestamps();

            $table->index(['order_id']);
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
        });
        }

        // ── Webhook Events (FR-ST-002 + FR-ST-005) ───────────────
        if (! Schema::hasTable('webhook_events')) {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('account_id');
            $table->uuid('store_id');

            $table->string('platform', 30);
            $table->string('event_type', 100)->comment('orders/create, orders/updated, etc.');
            $table->string('external_event_id', 255)->nullable()->comment('Idempotency key from platform');
            $table->string('external_resource_id', 200)->nullable()->comment('External order/resource ID');

            $table->enum('status', ['received', 'processing', 'processed', 'failed', 'duplicate', 'ignored'])
                  ->default('received');

            $table->json('payload')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            // Dedup: prevent processing same event twice
            $table->unique(['store_id', 'external_event_id'], 'webhook_events_dedup');
            $table->index(['account_id', 'store_id', 'status']);
            $table->index(['store_id', 'event_type', 'created_at']);
            $table->index('account_id');
            $table->index('store_id');
            // FKs omitted: accounts.id, stores.id may be bigint on server
        });
        }

        // ── Store Sync Log (FR-ST-003 + FR-ST-010) ───────────────
        if (! Schema::hasTable('store_sync_logs')) {
        Schema::create('store_sync_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('account_id');
            $table->uuid('store_id');

            $table->enum('sync_type', ['webhook', 'polling', 'manual'])->default('polling');
            $table->enum('status', ['started', 'completed', 'failed', 'partial'])->default('started');
            $table->integer('orders_found')->default(0);
            $table->integer('orders_imported')->default(0);
            $table->integer('orders_skipped')->default(0)->comment('Duplicates or filtered out');
            $table->integer('orders_failed')->default(0);
            $table->json('errors')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'created_at']);
            $table->index('account_id');
            $table->index('store_id');
            // FKs omitted: accounts.id, stores.id may be bigint on server
        });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('store_sync_logs');
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
