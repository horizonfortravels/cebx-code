<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BRP Module — Business Rules: Pricing
 * FR-BRP-001→008 (8 requirements)
 *
 * Tables:
 *   1. pricing_rule_sets     — FR-BRP-001/008: Versioned rule-set containers
 *   2. pricing_rules         — FR-BRP-002/003/004: Conditional pricing rules
 *   3. pricing_breakdowns    — FR-BRP-001/006: Stored pricing audit trail
 *   4. rounding_policies     — FR-BRP-005: Per-currency rounding config
 *   5. expired_plan_policies — FR-BRP-007: Alternative pricing on expired subscription
 */
return new class extends Migration
{
    public function up(): void
    {
        // ═══════════════════════════════════════════════════════════
        // 1. pricing_rule_sets — FR-BRP-001/008
        // ═══════════════════════════════════════════════════════════
        if (!Schema::hasTable('pricing_rule_sets')) {
        Schema::create('pricing_rule_sets', function (Blueprint $table) {
            $table->uuid('id')->primary();
                        $table->uuid('account_id')->nullable(); // null = platform default; FK omitted for server compatibility

            $table->string('name', 200);
            $table->integer('version')->default(1);
            $table->enum('status', ['draft', 'active', 'archived'])->default('draft');

            $table->boolean('is_default')->default(false);
            $table->text('description')->nullable();

            $table->timestamp('activated_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->string('created_by', 100)->nullable();

            $table->timestamps();

            $table->index(['account_id', 'status']);
            $table->index(['is_default', 'status']);
        });
        }

        // ═══════════════════════════════════════════════════════════
        // 2. pricing_rules — FR-BRP-002/003/004/008 (skipped if already from RT module)
        // ═══════════════════════════════════════════════════════════
        if (!Schema::hasTable('pricing_rules')) {
        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('rule_set_id')->constrained('pricing_rule_sets')->cascadeOnDelete();

            $table->string('name', 200);
            $table->enum('type', [
                'markup_percentage',    // Markup % on net rate
                'markup_fixed',         // Fixed amount added to net rate
                'service_fee_fixed',    // FR-BRP-003: Independent fixed fee
                'service_fee_percent',  // FR-BRP-003: Independent % fee
                'discount_percentage',  // Discount %
                'discount_fixed',       // Discount fixed
                'surcharge',            // Additional surcharge
                'min_price',            // FR-BRP-004: Minimum price guardrail
                'min_profit',           // FR-BRP-004: Minimum profit guardrail
            ]);

            $table->decimal('value', 12, 4);              // Amount or percentage
            $table->integer('priority')->default(100);     // FR-BRP-008: Lower = higher priority
            $table->boolean('is_cumulative')->default(false); // FR-BRP-008: Stack with other rules

            // ── Conditions (FR-BRP-002) ──────────────────────
            $table->json('conditions')->nullable();
            /*
             * Conditions structure:
             * {
             *   "carrier": ["DHL", "ARAMEX"],
             *   "service": ["express", "economy"],
             *   "destination_country": ["SA", "AE"],
             *   "origin_country": ["SA"],
             *   "zone": ["domestic", "international"],
             *   "weight_min": 0, "weight_max": 30,
             *   "shipment_type": ["standard", "cod", "return"],
             *   "store_id": ["store-1"],
             *   "plan_slug": ["pro", "enterprise"]
             * }
             */

            $table->boolean('is_fallback')->default(false);  // Default rule when no match
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['rule_set_id', 'priority']);
            $table->index(['type', 'is_active']);
        });
        }

        // ═══════════════════════════════════════════════════════════
        // 3. pricing_breakdowns — FR-BRP-001/006
        // ═══════════════════════════════════════════════════════════
        if (!Schema::hasTable('pricing_breakdowns')) {
        Schema::create('pricing_breakdowns', function (Blueprint $table) {
            $table->uuid('id')->primary();
                        $table->uuid('account_id');

            // ── Linkage (FR-BRP-006) ─────────────────────────
            $table->string('entity_type', 50);  // rate_quote, shipment
            $table->string('entity_id', 100);
            $table->string('correlation_id', 200);

            // ── Input Snapshot ───────────────────────────────
            $table->string('carrier_code', 50);
            $table->string('service_code', 100);
            $table->string('origin_country', 2)->nullable();
            $table->string('destination_country', 2)->nullable();
            $table->decimal('weight', 8, 2)->nullable();
            $table->string('zone', 50)->nullable();
            $table->string('shipment_type', 50)->default('standard');

            // ── Pricing Result ───────────────────────────────
            $table->decimal('net_rate', 12, 2);             // From carrier
            $table->decimal('markup_amount', 12, 2)->default(0);
            $table->decimal('service_fee', 12, 2)->default(0);    // FR-BRP-003
            $table->decimal('surcharge', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('pre_rounding_total', 12, 2);    // FR-BRP-005
            $table->decimal('retail_rate', 12, 2);            // Final after rounding

            // ── Audit Trail (FR-BRP-001) ─────────────────────
            $table->string('rule_set_id', 100)->nullable();
            $table->integer('rule_set_version')->nullable();
            $table->json('applied_rules');                    // Array of {rule_id, name, type, value, effect}
            $table->json('guardrail_adjustments')->nullable(); // FR-BRP-004: Any min price/profit applied
            $table->string('rounding_policy', 50)->nullable(); // FR-BRP-005
            $table->string('currency', 3)->default('SAR');

            // ── Subscription context (FR-BRP-007) ────────────
            $table->string('plan_slug', 50)->nullable();
            $table->boolean('expired_plan_surcharge')->default(false);

            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
            $table->index(['correlation_id']);
            $table->index(['account_id', 'created_at']);
        });
        }

        // ═══════════════════════════════════════════════════════════
        // 4. rounding_policies — FR-BRP-005
        // ═══════════════════════════════════════════════════════════
        if (!Schema::hasTable('rounding_policies')) {
        Schema::create('rounding_policies', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('currency', 3)->unique();
            $table->enum('method', ['up', 'down', 'nearest', 'none'])->default('nearest');
            $table->unsignedTinyInteger('precision')->default(2);   // Decimal places
            $table->decimal('step', 8, 4)->default(0.01);           // e.g. 0.50 = round to nearest 0.50

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        }

        // ═══════════════════════════════════════════════════════════
        // 5. expired_plan_policies — FR-BRP-007
        // ═══════════════════════════════════════════════════════════
        if (!Schema::hasTable('expired_plan_policies')) {
        Schema::create('expired_plan_policies', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('plan_slug', 50)->nullable();            // null = all plans
            $table->enum('policy_type', ['surcharge_percent', 'surcharge_fixed', 'markup_override']);
            $table->decimal('value', 12, 4);
            $table->text('reason_label')->nullable();               // Shown in breakdown

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('expired_plan_policies');
        Schema::dropIfExists('rounding_policies');
        Schema::dropIfExists('pricing_breakdowns');
        Schema::dropIfExists('pricing_rules');
        Schema::dropIfExists('pricing_rule_sets');
    }
};
