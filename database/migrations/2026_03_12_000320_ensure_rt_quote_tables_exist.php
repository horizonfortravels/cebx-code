<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('rate_quotes')) {
            Schema::create('rate_quotes', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('account_id');
                $table->uuid('shipment_id')->nullable();
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
                $table->enum('status', ['pending', 'completed', 'failed', 'expired', 'selected'])->default('pending');
                $table->integer('options_count')->default(0);
                $table->timestamp('expires_at')->nullable();
                $table->boolean('is_expired')->default(false);
                $table->uuid('selected_option_id')->nullable();
                $table->string('correlation_id', 100)->nullable();
                $table->json('request_metadata')->nullable();
                $table->string('error_message', 500)->nullable();
                $table->uuid('requested_by');
                $table->timestamps();

                $table->index(['account_id', 'status']);
                $table->index('shipment_id');
                $table->index('correlation_id');
                $table->index('requested_by');
            });
        }

        if (!Schema::hasTable('rate_options')) {
            Schema::create('rate_options', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('rate_quote_id');
                $table->string('carrier_code', 50);
                $table->string('carrier_name', 100);
                $table->string('service_code', 50);
                $table->string('service_name', 200);
                $table->decimal('net_rate', 15, 2);
                $table->decimal('fuel_surcharge', 15, 2)->default(0);
                $table->decimal('other_surcharges', 15, 2)->default(0);
                $table->decimal('total_net_rate', 15, 2);
                $table->decimal('markup_amount', 15, 2)->default(0);
                $table->decimal('service_fee', 15, 2)->default(0);
                $table->decimal('retail_rate_before_rounding', 15, 2);
                $table->decimal('retail_rate', 15, 2);
                $table->decimal('profit_margin', 15, 2)->default(0);
                $table->string('currency', 3)->default('SAR');
                $table->integer('estimated_days_min')->nullable();
                $table->integer('estimated_days_max')->nullable();
                $table->timestamp('estimated_delivery_at')->nullable();
                $table->boolean('is_cheapest')->default(false);
                $table->boolean('is_fastest')->default(false);
                $table->boolean('is_best_value')->default(false);
                $table->boolean('is_recommended')->default(false);
                $table->uuid('pricing_rule_id')->nullable();
                $table->json('pricing_breakdown')->nullable();
                $table->json('rule_evaluation_log')->nullable();
                $table->boolean('is_available')->default(true);
                $table->string('unavailable_reason', 300)->nullable();
                $table->timestamps();

                $table->index('rate_quote_id');
                $table->index('pricing_rule_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_options');
        Schema::dropIfExists('rate_quotes');
    }
};
