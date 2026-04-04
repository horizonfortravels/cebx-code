<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pricing_rules')) {
            $this->addColumnIfMissing('pricing_rules', 'account_id', static function (Blueprint $table): void {
                $table->uuid('account_id')->nullable();
            });
            $this->addColumnIfMissing('pricing_rules', 'rule_set_id', static function (Blueprint $table): void {
                $table->uuid('rule_set_id')->nullable();
            });
            $this->addColumnIfMissing('pricing_rules', 'name', static function (Blueprint $table): void {
                $table->string('name', 200)->nullable();
            });
            $this->addColumnIfMissing('pricing_rules', 'description', static function (Blueprint $table): void {
                $table->text('description')->nullable();
            });
            $this->addColumnIfMissing('pricing_rules', 'service_code', static function (Blueprint $table): void {
                $table->string('service_code', 50)->nullable();
            });
            $this->addColumnIfMissing('pricing_rules', 'origin_country', static function (Blueprint $table): void {
                $table->string('origin_country', 2)->nullable();
            });
            $this->addColumnIfMissing('pricing_rules', 'destination_country', static function (Blueprint $table): void {
                $table->string('destination_country', 2)->nullable();
            });
            $this->addColumnIfMissing('pricing_rules', 'destination_zone', static function (Blueprint $table): void {
                $table->string('destination_zone', 50)->nullable();
            });
            $this->addColumnIfMissing('pricing_rules', 'shipment_type', static function (Blueprint $table): void {
                $table->string('shipment_type', 32)->nullable();
            });
            $this->addColumnIfMissing('pricing_rules', 'min_weight', static function (Blueprint $table): void {
                $table->decimal('min_weight', 10, 3)->nullable();
            });
            $this->addColumnIfMissing('pricing_rules', 'max_weight', static function (Blueprint $table): void {
                $table->decimal('max_weight', 10, 3)->nullable();
            });
            $this->addColumnIfMissing('pricing_rules', 'store_id', static function (Blueprint $table): void {
                $table->uuid('store_id')->nullable();
            });
            $this->addColumnIfMissing('pricing_rules', 'is_cod', static function (Blueprint $table): void {
                $table->boolean('is_cod')->nullable();
            });
            $this->addColumnIfMissing('pricing_rules', 'markup_type', static function (Blueprint $table): void {
                $table->string('markup_type', 32)->default('percentage');
            });
            $this->addColumnIfMissing('pricing_rules', 'markup_percentage', static function (Blueprint $table): void {
                $table->decimal('markup_percentage', 8, 4)->default(0);
            });
            $this->addColumnIfMissing('pricing_rules', 'markup_fixed', static function (Blueprint $table): void {
                $table->decimal('markup_fixed', 15, 2)->default(0);
            });
            $this->addColumnIfMissing('pricing_rules', 'min_profit', static function (Blueprint $table): void {
                $table->decimal('min_profit', 15, 2)->default(0);
            });
            $this->addColumnIfMissing('pricing_rules', 'min_retail_price', static function (Blueprint $table): void {
                $table->decimal('min_retail_price', 15, 2)->default(0);
            });
            $this->addColumnIfMissing('pricing_rules', 'max_retail_price', static function (Blueprint $table): void {
                $table->decimal('max_retail_price', 15, 2)->nullable();
            });
            $this->addColumnIfMissing('pricing_rules', 'service_fee_fixed', static function (Blueprint $table): void {
                $table->decimal('service_fee_fixed', 15, 2)->default(0);
            });
            $this->addColumnIfMissing('pricing_rules', 'service_fee_percentage', static function (Blueprint $table): void {
                $table->decimal('service_fee_percentage', 8, 4)->default(0);
            });
            $this->addColumnIfMissing('pricing_rules', 'rounding_mode', static function (Blueprint $table): void {
                $table->string('rounding_mode', 32)->default('round');
            });
            $this->addColumnIfMissing('pricing_rules', 'rounding_precision', static function (Blueprint $table): void {
                $table->decimal('rounding_precision', 5, 2)->default(1);
            });
            $this->addColumnIfMissing('pricing_rules', 'is_expired_surcharge', static function (Blueprint $table): void {
                $table->boolean('is_expired_surcharge')->default(false);
            });
            $this->addColumnIfMissing('pricing_rules', 'expired_surcharge_percentage', static function (Blueprint $table): void {
                $table->decimal('expired_surcharge_percentage', 8, 4)->default(0);
            });
            $this->addColumnIfMissing('pricing_rules', 'priority', static function (Blueprint $table): void {
                $table->integer('priority')->default(100);
            });
            $this->addColumnIfMissing('pricing_rules', 'is_default', static function (Blueprint $table): void {
                $table->boolean('is_default')->default(false);
            });
            $this->addColumnIfMissing('pricing_rules', 'currency', static function (Blueprint $table): void {
                $table->string('currency', 3)->default('SAR');
            });
        }

        if (Schema::hasTable('pricing_breakdowns')) {
            $this->addColumnIfMissing('pricing_breakdowns', 'shipment_id', static function (Blueprint $table): void {
                $table->uuid('shipment_id')->nullable();
            });
            $this->addColumnIfMissing('pricing_breakdowns', 'rate_quote_id', static function (Blueprint $table): void {
                $table->uuid('rate_quote_id')->nullable();
            });
            $this->addColumnIfMissing('pricing_breakdowns', 'rate_option_id', static function (Blueprint $table): void {
                $table->uuid('rate_option_id')->nullable();
            });
            $this->addColumnIfMissing('pricing_breakdowns', 'pricing_stage', static function (Blueprint $table): void {
                $table->string('pricing_stage', 32)->nullable();
            });
            $this->addColumnIfMissing('pricing_breakdowns', 'carrier_net_rate', static function (Blueprint $table): void {
                $table->decimal('carrier_net_rate', 12, 2)->default(0);
            });
            $this->addColumnIfMissing('pricing_breakdowns', 'fuel_surcharge', static function (Blueprint $table): void {
                $table->decimal('fuel_surcharge', 12, 2)->default(0);
            });
            $this->addColumnIfMissing('pricing_breakdowns', 'other_surcharges', static function (Blueprint $table): void {
                $table->decimal('other_surcharges', 12, 2)->default(0);
            });
            $this->addColumnIfMissing('pricing_breakdowns', 'rounding_adjustment', static function (Blueprint $table): void {
                $table->decimal('rounding_adjustment', 12, 2)->default(0);
            });
            $this->addColumnIfMissing('pricing_breakdowns', 'minimum_charge_adjustment', static function (Blueprint $table): void {
                $table->decimal('minimum_charge_adjustment', 12, 2)->default(0);
            });
            $this->addColumnIfMissing('pricing_breakdowns', 'canonical_engine', static function (Blueprint $table): void {
                $table->string('canonical_engine', 255)->nullable();
            });
            $this->addColumnIfMissing('pricing_breakdowns', 'pricing_path', static function (Blueprint $table): void {
                $table->string('pricing_path', 64)->nullable();
            });
        }

        $this->addColumnIfMissing('rate_options', 'pricing_breakdown_id', static function (Blueprint $table): void {
            $table->uuid('pricing_breakdown_id')->nullable();
        });
    }

    private function addColumnIfMissing(string $table, string $column, callable $callback): void
    {
        if (! Schema::hasTable($table) || Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($callback): void {
            $callback($blueprint);
        });
    }

    public function down(): void
    {
        // Forward-only by policy: previously-run historical migrations must not be edited
        // and schema cleanup should happen through explicit future migrations if required.
    }
};
