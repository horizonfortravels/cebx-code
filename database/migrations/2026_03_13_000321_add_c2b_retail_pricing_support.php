<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pricing_rules')) {
            Schema::table('pricing_rules', function (Blueprint $table): void {
                if (!Schema::hasColumn('pricing_rules', 'account_id')) {
                    $table->uuid('account_id')->nullable()->after('id');
                }
                if (!Schema::hasColumn('pricing_rules', 'rule_set_id')) {
                    $table->uuid('rule_set_id')->nullable()->after('account_id');
                }
                if (!Schema::hasColumn('pricing_rules', 'name')) {
                    $table->string('name', 200)->nullable()->after('rule_set_id');
                }
                if (!Schema::hasColumn('pricing_rules', 'description')) {
                    $table->text('description')->nullable()->after('name');
                }
                if (!Schema::hasColumn('pricing_rules', 'service_code')) {
                    $table->string('service_code', 50)->nullable()->after('carrier_name');
                }
                if (!Schema::hasColumn('pricing_rules', 'origin_country')) {
                    $table->string('origin_country', 2)->nullable()->after('service_code');
                }
                if (!Schema::hasColumn('pricing_rules', 'destination_country')) {
                    $table->string('destination_country', 2)->nullable()->after('origin_country');
                }
                if (!Schema::hasColumn('pricing_rules', 'destination_zone')) {
                    $table->string('destination_zone', 50)->nullable()->after('destination_country');
                }
                if (!Schema::hasColumn('pricing_rules', 'shipment_type')) {
                    $table->string('shipment_type', 32)->nullable()->after('destination_zone');
                }
                if (!Schema::hasColumn('pricing_rules', 'min_weight')) {
                    $table->decimal('min_weight', 10, 3)->nullable()->after('shipment_type');
                }
                if (!Schema::hasColumn('pricing_rules', 'max_weight')) {
                    $table->decimal('max_weight', 10, 3)->nullable()->after('min_weight');
                }
                if (!Schema::hasColumn('pricing_rules', 'store_id')) {
                    $table->uuid('store_id')->nullable()->after('max_weight');
                }
                if (!Schema::hasColumn('pricing_rules', 'is_cod')) {
                    $table->boolean('is_cod')->nullable()->after('store_id');
                }
                if (!Schema::hasColumn('pricing_rules', 'markup_type')) {
                    $table->string('markup_type', 32)->default('percentage')->after('is_cod');
                }
                if (!Schema::hasColumn('pricing_rules', 'markup_percentage')) {
                    $table->decimal('markup_percentage', 8, 4)->default(0)->after('markup_type');
                }
                if (!Schema::hasColumn('pricing_rules', 'markup_fixed')) {
                    $table->decimal('markup_fixed', 15, 2)->default(0)->after('markup_percentage');
                }
                if (!Schema::hasColumn('pricing_rules', 'min_profit')) {
                    $table->decimal('min_profit', 15, 2)->default(0)->after('markup_fixed');
                }
                if (!Schema::hasColumn('pricing_rules', 'min_retail_price')) {
                    $table->decimal('min_retail_price', 15, 2)->default(0)->after('min_profit');
                }
                if (!Schema::hasColumn('pricing_rules', 'max_retail_price')) {
                    $table->decimal('max_retail_price', 15, 2)->nullable()->after('min_retail_price');
                }
                if (!Schema::hasColumn('pricing_rules', 'service_fee_fixed')) {
                    $table->decimal('service_fee_fixed', 15, 2)->default(0)->after('max_retail_price');
                }
                if (!Schema::hasColumn('pricing_rules', 'service_fee_percentage')) {
                    $table->decimal('service_fee_percentage', 8, 4)->default(0)->after('service_fee_fixed');
                }
                if (!Schema::hasColumn('pricing_rules', 'rounding_mode')) {
                    $table->string('rounding_mode', 32)->default('round')->after('service_fee_percentage');
                }
                if (!Schema::hasColumn('pricing_rules', 'rounding_precision')) {
                    $table->decimal('rounding_precision', 5, 2)->default(1)->after('rounding_mode');
                }
                if (!Schema::hasColumn('pricing_rules', 'is_expired_surcharge')) {
                    $table->boolean('is_expired_surcharge')->default(false)->after('rounding_precision');
                }
                if (!Schema::hasColumn('pricing_rules', 'expired_surcharge_percentage')) {
                    $table->decimal('expired_surcharge_percentage', 8, 4)->default(0)->after('is_expired_surcharge');
                }
                if (!Schema::hasColumn('pricing_rules', 'priority')) {
                    $table->integer('priority')->default(100)->after('expired_surcharge_percentage');
                }
                if (!Schema::hasColumn('pricing_rules', 'is_default')) {
                    $table->boolean('is_default')->default(false)->after('priority');
                }
                if (!Schema::hasColumn('pricing_rules', 'currency')) {
                    $table->string('currency', 3)->default('SAR')->after('is_default');
                }
            });
        }

        if (Schema::hasTable('pricing_breakdowns')) {
            Schema::table('pricing_breakdowns', function (Blueprint $table): void {
                if (!Schema::hasColumn('pricing_breakdowns', 'shipment_id')) {
                    $table->uuid('shipment_id')->nullable()->after('account_id');
                }
                if (!Schema::hasColumn('pricing_breakdowns', 'rate_quote_id')) {
                    $table->uuid('rate_quote_id')->nullable()->after('shipment_id');
                }
                if (!Schema::hasColumn('pricing_breakdowns', 'rate_option_id')) {
                    $table->uuid('rate_option_id')->nullable()->after('rate_quote_id');
                }
                if (!Schema::hasColumn('pricing_breakdowns', 'pricing_stage')) {
                    $table->string('pricing_stage', 32)->nullable()->after('correlation_id');
                }
                if (!Schema::hasColumn('pricing_breakdowns', 'carrier_net_rate')) {
                    $table->decimal('carrier_net_rate', 12, 2)->default(0)->after('shipment_type');
                }
                if (!Schema::hasColumn('pricing_breakdowns', 'fuel_surcharge')) {
                    $table->decimal('fuel_surcharge', 12, 2)->default(0)->after('carrier_net_rate');
                }
                if (!Schema::hasColumn('pricing_breakdowns', 'other_surcharges')) {
                    $table->decimal('other_surcharges', 12, 2)->default(0)->after('fuel_surcharge');
                }
                if (!Schema::hasColumn('pricing_breakdowns', 'rounding_adjustment')) {
                    $table->decimal('rounding_adjustment', 12, 2)->default(0)->after('pre_rounding_total');
                }
                if (!Schema::hasColumn('pricing_breakdowns', 'minimum_charge_adjustment')) {
                    $table->decimal('minimum_charge_adjustment', 12, 2)->default(0)->after('rounding_adjustment');
                }
                if (!Schema::hasColumn('pricing_breakdowns', 'canonical_engine')) {
                    $table->string('canonical_engine', 255)->nullable()->after('currency');
                }
                if (!Schema::hasColumn('pricing_breakdowns', 'pricing_path')) {
                    $table->string('pricing_path', 64)->nullable()->after('canonical_engine');
                }
            });
        }

        if (Schema::hasTable('rate_options')) {
            Schema::table('rate_options', function (Blueprint $table): void {
                if (!Schema::hasColumn('rate_options', 'pricing_breakdown_id')) {
                    $table->uuid('pricing_breakdown_id')->nullable()->after('pricing_rule_id');
                }
            });
        }
    }

    public function down(): void
    {
        // Forward-only by policy: previously-run historical migrations must not be edited
        // and schema cleanup should happen through explicit future migrations if required.
    }
};
