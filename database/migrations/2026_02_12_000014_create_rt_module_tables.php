<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RT Module — Rates & Pricing
 *
 * FR-RT-001→012: Rate fetching, markup, pricing rules, quotes
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pricing_rules')) {
            return;
        }

        // ── Pricing Rules (FR-RT-002/003/008) ────────────────────
        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('account_id')->nullable()->comment('null = platform-wide default');
            $table->string('name', 200);
            $table->text('description')->nullable();

            // Conditions (FR-RT-008)
            $table->string('carrier_code', 50)->nullable()->comment('null = any carrier');
            $table->string('service_code', 50)->nullable()->comment('null = any service');
            $table->string('origin_country', 2)->nullable();
            $table->string('destination_country', 2)->nullable();
            $table->string('destination_zone', 50)->nullable()->comment('e.g. GCC, EU, NA');
            $table->enum('shipment_type', ['any', 'domestic', 'international'])->default('any');
            $table->decimal('min_weight', 10, 3)->nullable()->comment('kg');
            $table->decimal('max_weight', 10, 3)->nullable();
            $table->uuid('store_id')->nullable()->comment('null = any store');
            $table->boolean('is_cod')->nullable()->comment('null = any');

            // Markup Configuration (FR-RT-003)
            $table->enum('markup_type', ['percentage', 'fixed', 'both'])->default('percentage');
            $table->decimal('markup_percentage', 8, 4)->default(0)->comment('e.g. 15.0000 = 15%');
            $table->decimal('markup_fixed', 15, 2)->default(0)->comment('Fixed amount added');
            $table->decimal('min_profit', 15, 2)->default(0)->comment('Minimum profit margin');
            $table->decimal('min_retail_price', 15, 2)->default(0)->comment('Floor price');
            $table->decimal('max_retail_price', 15, 2)->nullable()->comment('Ceiling price');

            // Service Fee (FR-BRP-003)
            $table->decimal('service_fee_fixed', 15, 2)->default(0);
            $table->decimal('service_fee_percentage', 8, 4)->default(0);

            // Rounding (FR-RT-004)
            $table->enum('rounding_mode', ['none', 'ceil', 'floor', 'round'])->default('round');
            $table->decimal('rounding_precision', 5, 2)->default(1)->comment('Round to nearest X');

            // Subscription surcharge (FR-RT-009/BRP-007)
            $table->boolean('is_expired_surcharge')->default(false);
            $table->decimal('expired_surcharge_percentage', 8, 4)->default(0);

            // Priority & Status
            $table->integer('priority')->default(100)->comment('Lower = higher priority');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false)->comment('Fallback rule');
            $table->string('currency', 3)->default('SAR');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['account_id', 'is_active', 'priority']);
            $table->index(['carrier_code', 'service_code']);
            $table->index('store_id');
        });

        // ── Rate Quotes (FR-RT-001/005/006/007) ──────────────────
        if (! Schema::hasTable('rate_quotes')) {
        Schema::create('rate_quotes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('account_id');
            $table->uuid('shipment_id')->nullable();

            // Request context
            $table->string('origin_country', 2);
            $table->string('origin_city', 100)->nullable();
            $table->string('destination_country', 2);
            $table->string('destination_city', 100)->nullable();
            $table->decimal('total_weight', 10, 3);
            $table->decimal('chargeable_weight', 10, 3)->nullable();
            $table->integer('parcels_count')->default(1);
            $table->boolean('is_cod')->default(false);
            $table->decimal('cod_amount', 15, 2)->default(0);
            $table->boolean('is_insured')->default(false);
            $table->decimal('insurance_value', 15, 2)->default(0);
            $table->string('currency', 3)->default('SAR');

            // Status
            $table->enum('status', ['pending', 'completed', 'failed', 'expired', 'selected'])->default('pending');
            $table->integer('options_count')->default(0);

            // TTL (FR-RT-007)
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_expired')->default(false);

            // Selected option
            $table->uuid('selected_option_id')->nullable();

            // Metadata
            $table->string('correlation_id', 100)->nullable()->comment('FR-BRP-001: Traceability');
            $table->json('request_metadata')->nullable();
            $table->string('error_message', 500)->nullable();

            $table->uuid('requested_by');

            $table->timestamps();

            $table->index(['account_id', 'status']);
            $table->index(['shipment_id']);
            $table->index('correlation_id');
            $table->index('requested_by');
            // FKs omitted: accounts.id, shipments.id, users.id may be bigint on server
        });
        }

        // ── Rate Options (individual carrier/service quotes) ─────
        if (! Schema::hasTable('rate_options')) {
        Schema::create('rate_options', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('rate_quote_id');

            // Carrier & Service
            $table->string('carrier_code', 50);
            $table->string('carrier_name', 100);
            $table->string('service_code', 50);
            $table->string('service_name', 200);

            // Net Rate (from carrier) — FR-RT-001
            $table->decimal('net_rate', 15, 2)->comment('Carrier base rate');
            $table->decimal('fuel_surcharge', 15, 2)->default(0);
            $table->decimal('other_surcharges', 15, 2)->default(0);
            $table->decimal('total_net_rate', 15, 2)->comment('net_rate + surcharges');

            // Retail Rate (after markup) — FR-RT-002
            $table->decimal('markup_amount', 15, 2)->default(0);
            $table->decimal('service_fee', 15, 2)->default(0);
            $table->decimal('retail_rate_before_rounding', 15, 2);
            $table->decimal('retail_rate', 15, 2)->comment('Final price to customer');

            // Profit
            $table->decimal('profit_margin', 15, 2)->default(0);
            $table->string('currency', 3)->default('SAR');

            // Delivery estimates
            $table->integer('estimated_days_min')->nullable();
            $table->integer('estimated_days_max')->nullable();
            $table->timestamp('estimated_delivery_at')->nullable();

            // Badges (FR-RT-006)
            $table->boolean('is_cheapest')->default(false);
            $table->boolean('is_fastest')->default(false);
            $table->boolean('is_best_value')->default(false);
            $table->boolean('is_recommended')->default(false);

            // Rule applied (FR-BRP-001 Explainable Pricing)
            $table->uuid('pricing_rule_id')->nullable();
            $table->json('pricing_breakdown')->nullable()->comment('FR-RT-005: Detailed breakdown');
            $table->json('rule_evaluation_log')->nullable();

            $table->boolean('is_available')->default(true);
            $table->string('unavailable_reason', 300)->nullable();

            $table->timestamps();

            $table->index('rate_quote_id');
            $table->index('pricing_rule_id');
            // FK omitted for server compatibility
        });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_options');
        Schema::dropIfExists('rate_quotes');
        Schema::dropIfExists('pricing_rules');
    }
};
