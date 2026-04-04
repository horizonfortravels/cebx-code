<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table): void {
                if (! Schema::hasColumn('orders', 'external_order_id')) {
                    $table->string('external_order_id', 200)->nullable()->after('store_id');
                }
                if (! Schema::hasColumn('orders', 'external_order_number')) {
                    $table->string('external_order_number', 100)->nullable()->after('external_order_id');
                }
                if (! Schema::hasColumn('orders', 'source')) {
                    $table->string('source', 30)->default('manual')->after('external_order_number');
                }
                if (! Schema::hasColumn('orders', 'customer_email')) {
                    $table->string('customer_email', 255)->nullable()->after('customer_name');
                }
                if (! Schema::hasColumn('orders', 'shipping_name')) {
                    $table->string('shipping_name', 200)->nullable()->after('customer_phone');
                }
                if (! Schema::hasColumn('orders', 'shipping_phone')) {
                    $table->string('shipping_phone', 30)->nullable()->after('shipping_name');
                }
                if (! Schema::hasColumn('orders', 'shipping_address_line_1')) {
                    $table->string('shipping_address_line_1', 300)->nullable()->after('shipping_phone');
                }
                if (! Schema::hasColumn('orders', 'shipping_address_line_2')) {
                    $table->string('shipping_address_line_2', 300)->nullable()->after('shipping_address_line_1');
                }
                if (! Schema::hasColumn('orders', 'shipping_city')) {
                    $table->string('shipping_city', 100)->nullable()->after('shipping_address_line_2');
                }
                if (! Schema::hasColumn('orders', 'shipping_state')) {
                    $table->string('shipping_state', 100)->nullable()->after('shipping_city');
                }
                if (! Schema::hasColumn('orders', 'shipping_postal_code')) {
                    $table->string('shipping_postal_code', 20)->nullable()->after('shipping_state');
                }
                if (! Schema::hasColumn('orders', 'shipping_country')) {
                    $table->string('shipping_country', 2)->nullable()->after('shipping_postal_code');
                }
                if (! Schema::hasColumn('orders', 'subtotal')) {
                    $table->decimal('subtotal', 15, 2)->default(0)->after('shipping_country');
                }
                if (! Schema::hasColumn('orders', 'shipping_cost')) {
                    $table->decimal('shipping_cost', 15, 2)->default(0)->after('subtotal');
                }
                if (! Schema::hasColumn('orders', 'tax_amount')) {
                    $table->decimal('tax_amount', 15, 2)->default(0)->after('shipping_cost');
                }
                if (! Schema::hasColumn('orders', 'discount_amount')) {
                    $table->decimal('discount_amount', 15, 2)->default(0)->after('tax_amount');
                }
                if (! Schema::hasColumn('orders', 'currency')) {
                    $table->string('currency', 3)->default('SAR')->after('total_amount');
                }
                if (! Schema::hasColumn('orders', 'total_weight')) {
                    $table->decimal('total_weight', 10, 3)->nullable()->after('currency');
                }
                if (! Schema::hasColumn('orders', 'auto_ship_eligible')) {
                    $table->boolean('auto_ship_eligible')->default(false)->after('shipment_id');
                }
                if (! Schema::hasColumn('orders', 'hold_reason')) {
                    $table->string('hold_reason', 500)->nullable()->after('auto_ship_eligible');
                }
                if (! Schema::hasColumn('orders', 'rule_evaluation_log')) {
                    $table->json('rule_evaluation_log')->nullable()->after('hold_reason');
                }
                if (! Schema::hasColumn('orders', 'raw_payload')) {
                    $table->json('raw_payload')->nullable()->after('rule_evaluation_log');
                }
                if (! Schema::hasColumn('orders', 'metadata')) {
                    $table->json('metadata')->nullable()->after('raw_payload');
                }
                if (! Schema::hasColumn('orders', 'external_created_at')) {
                    $table->timestamp('external_created_at')->nullable()->after('metadata');
                }
                if (! Schema::hasColumn('orders', 'external_updated_at')) {
                    $table->timestamp('external_updated_at')->nullable()->after('external_created_at');
                }
                if (! Schema::hasColumn('orders', 'imported_at')) {
                    $table->timestamp('imported_at')->nullable()->after('external_updated_at');
                }
                if (! Schema::hasColumn('orders', 'imported_by')) {
                    $table->uuid('imported_by')->nullable()->after('imported_at');
                }
            });

            if (Schema::hasColumn('orders', 'order_number')) {
                DB::statement('ALTER TABLE `orders` MODIFY `order_number` VARCHAR(255) NULL');
            }
        }

        if (! Schema::hasTable('order_items')) {
            Schema::create('order_items', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('order_id');
                $table->string('external_item_id', 200)->nullable();
                $table->string('sku', 100)->nullable();
                $table->string('name', 300);
                $table->integer('quantity')->default(1);
                $table->decimal('unit_price', 15, 2)->default(0);
                $table->decimal('total_price', 15, 2)->default(0);
                $table->decimal('weight', 10, 3)->nullable();
                $table->string('hs_code', 20)->nullable();
                $table->string('country_of_origin', 2)->nullable();
                $table->json('properties')->nullable();
                $table->timestamps();

                $table->index(['order_id']);
            });
        }

        if (! Schema::hasTable('store_sync_logs')) {
            Schema::create('store_sync_logs', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('account_id');
                $table->uuid('store_id');
                $table->enum('sync_type', ['webhook', 'polling', 'manual'])->default('polling');
                $table->enum('status', ['started', 'completed', 'failed', 'partial'])->default('started');
                $table->integer('orders_found')->default(0);
                $table->integer('orders_imported')->default(0);
                $table->integer('orders_skipped')->default(0);
                $table->integer('orders_failed')->default(0);
                $table->json('errors')->nullable();
                $table->integer('retry_count')->default(0);
                $table->timestamp('started_at');
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['store_id', 'created_at']);
                $table->index('account_id');
                $table->index('store_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('store_sync_logs');
        Schema::dropIfExists('order_items');
    }
};
