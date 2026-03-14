<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ═══════════════════════════════════════════════════════════
        // 1. ROUTE OPTIMIZATION ENGINE
        // ═══════════════════════════════════════════════════════════

        if (! Schema::hasTable('route_plans')) {
            Schema::create('route_plans', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->uuid('account_id')->index();
                $t->uuid('shipment_id')->nullable()->index();
                $t->string('origin_code', 10);        // IATA/UN-LOCODE
                $t->string('destination_code', 10);
                $t->string('mode', 20);                // air / sea / land / multimodal
                $t->integer('legs_count')->default(1);
                $t->decimal('total_cost', 14, 2)->nullable();
                $t->decimal('total_distance_km', 10, 2)->nullable();
                $t->integer('total_transit_hours')->nullable();
                $t->decimal('co2_kg', 10, 2)->nullable();
                $t->string('optimization_strategy', 30)->default('cost'); // cost / speed / balanced / green
                $t->boolean('is_selected')->default(false);
                $t->json('metadata')->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('route_legs')) {
            Schema::create('route_legs', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('route_plan_id')->index();
            $t->integer('sequence');
            $t->string('origin_code', 10);
            $t->string('destination_code', 10);
            $t->string('transport_mode', 20);       // air / sea / road / rail
            $t->string('carrier_code', 20)->nullable();
            $t->string('carrier_name', 100)->nullable();
            $t->string('service_number', 50)->nullable(); // flight / voyage / truck
            $t->decimal('cost', 14, 2)->nullable();
            $t->decimal('distance_km', 10, 2)->nullable();
            $t->integer('transit_hours')->nullable();
            $t->timestamp('departure_at')->nullable();
            $t->timestamp('arrival_at')->nullable();
            $t->string('status', 30)->default('planned');
            $t->json('metadata')->nullable();
            $t->timestamps();
            $t->foreign('route_plan_id')->references('id')->on('route_plans')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('route_cost_factors')) {
            Schema::create('route_cost_factors', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('account_id')->index();
            $t->string('factor_name', 50);          // fuel / handling / insurance / customs / last_mile
            $t->string('origin_region', 10)->nullable();
            $t->string('destination_region', 10)->nullable();
            $t->string('transport_mode', 20)->nullable();
            $t->decimal('base_cost', 14, 2);
            $t->decimal('per_kg_cost', 10, 4)->nullable();
            $t->decimal('per_cbm_cost', 10, 4)->nullable();
            $t->decimal('percentage', 8, 4)->nullable();  // surcharge %
            $t->date('effective_from');
            $t->date('effective_to')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            });
        }

        // ═══════════════════════════════════════════════════════════
        // 2. CAPACITY & LOAD MANAGEMENT
        // ═══════════════════════════════════════════════════════════

        if (! Schema::hasTable('capacity_pools')) {
            Schema::create('capacity_pools', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('account_id')->index();
            $t->uuid('branch_id')->nullable()->index();
            $t->string('pool_type', 30);             // aircraft / vessel / warehouse / truck
            $t->string('carrier_code', 20)->nullable();
            $t->string('resource_name', 100);        // Flight EK123 / MSC Vessel / Truck PLT-001
            $t->string('route', 100)->nullable();     // JED→DXB
            $t->decimal('total_weight_kg', 12, 2);
            $t->decimal('used_weight_kg', 12, 2)->default(0);
            $t->decimal('total_volume_cbm', 10, 2)->nullable();
            $t->decimal('used_volume_cbm', 10, 2)->default(0);
            $t->integer('total_pieces')->nullable();
            $t->integer('used_pieces')->default(0);
            $t->decimal('overbooking_percent', 5, 2)->default(0);
            $t->timestamp('cutoff_at')->nullable();
            $t->date('departure_date')->nullable();
            $t->string('status', 20)->default('open');   // open / full / closed / departed
            $t->json('metadata')->nullable();
            $t->timestamps();
            });
        }

        if (! Schema::hasTable('capacity_bookings')) {
            Schema::create('capacity_bookings', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('capacity_pool_id')->index();
            $t->uuid('shipment_id')->index();
            $t->decimal('booked_weight_kg', 10, 2);
            $t->decimal('booked_volume_cbm', 10, 2)->nullable();
            $t->integer('booked_pieces')->default(1);
            $t->string('status', 20)->default('confirmed'); // confirmed / waitlisted / bumped / loaded
            $t->timestamps();
            $t->foreign('capacity_pool_id')->references('id')->on('capacity_pools')->cascadeOnDelete();
            });
        }

        // ═══════════════════════════════════════════════════════════
        // 3. PROFITABILITY ENGINE
        // ═══════════════════════════════════════════════════════════

        if (! Schema::hasTable('shipment_costs')) {
            Schema::create('shipment_costs', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('account_id')->index();
            $t->uuid('shipment_id')->unique();
            $t->decimal('revenue', 14, 2)->default(0);
            $t->decimal('carrier_cost', 14, 2)->default(0);
            $t->decimal('customs_cost', 14, 2)->default(0);
            $t->decimal('handling_cost', 14, 2)->default(0);
            $t->decimal('insurance_cost', 14, 2)->default(0);
            $t->decimal('last_mile_cost', 14, 2)->default(0);
            $t->decimal('other_cost', 14, 2)->default(0);
            $t->decimal('total_cost', 14, 2)->default(0);
            $t->decimal('gross_profit', 14, 2)->default(0);
            $t->decimal('margin_percent', 8, 2)->default(0);
            $t->string('currency', 3)->default('SAR');
            $t->json('cost_breakdown')->nullable();
            $t->timestamps();
            });
        }

        if (! Schema::hasTable('branch_pnl')) {
            Schema::create('branch_pnl', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('account_id')->index();
            $t->uuid('branch_id')->index();
            $t->date('period_start');
            $t->date('period_end');
            $t->string('period_type', 10);           // daily / weekly / monthly
            $t->integer('shipments_count')->default(0);
            $t->decimal('revenue', 14, 2)->default(0);
            $t->decimal('total_cost', 14, 2)->default(0);
            $t->decimal('gross_profit', 14, 2)->default(0);
            $t->decimal('margin_percent', 8, 2)->default(0);
            $t->decimal('avg_revenue_per_shipment', 10, 2)->default(0);
            $t->decimal('avg_cost_per_shipment', 10, 2)->default(0);
            $t->json('breakdown')->nullable();
            $t->timestamps();
            $t->unique(['branch_id', 'period_start', 'period_type']);
            });
        }

        // ═══════════════════════════════════════════════════════════
        // 4. MULTI-CURRENCY LEDGER
        // ═══════════════════════════════════════════════════════════

        if (! Schema::hasTable('exchange_rates')) {
            Schema::create('exchange_rates', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('from_currency', 3);
            $t->string('to_currency', 3);
            $t->decimal('rate', 18, 8);
            $t->string('source', 30)->default('manual'); // manual / api / bank
            $t->date('effective_date');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->index(['from_currency', 'to_currency', 'effective_date']);
            });
        }

        if (! Schema::hasTable('currency_transactions')) {
            Schema::create('currency_transactions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('account_id')->index();
            $t->string('entity_type', 50);            // invoice / payment / refund
            $t->uuid('entity_id');
            $t->decimal('original_amount', 14, 2);
            $t->string('original_currency', 3);
            $t->decimal('converted_amount', 14, 2);
            $t->string('target_currency', 3);
            $t->decimal('exchange_rate', 18, 8);
            $t->decimal('fx_gain_loss', 14, 2)->default(0);
            $t->timestamp('converted_at');
            $t->timestamps();
            $t->index(['entity_type', 'entity_id']);
            });
        }

        // ═══════════════════════════════════════════════════════════
        // 5. IATA / FIATA COMPLIANCE LAYER
        // ═══════════════════════════════════════════════════════════

        if (! Schema::hasTable('transport_documents')) {
            Schema::create('transport_documents', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('account_id')->index();
            $t->uuid('shipment_id')->index();
            $t->string('document_type', 30);          // AWB / MAWB / HAWB / BOL / CMR / CIM
            $t->string('document_number', 50)->unique();
            $t->string('issuer', 100)->nullable();
            $t->string('origin_code', 10);
            $t->string('destination_code', 10);
            $t->integer('pieces');
            $t->decimal('gross_weight_kg', 10, 2);
            $t->decimal('chargeable_weight_kg', 10, 2)->nullable();
            $t->decimal('declared_value', 14, 2)->nullable();
            $t->string('declared_value_currency', 3)->nullable();
            $t->text('goods_description')->nullable();
            $t->string('handling_codes', 100)->nullable(); // SPH codes
            $t->boolean('is_validated')->default(false);
            $t->json('validation_errors')->nullable();
            $t->string('status', 20)->default('draft');
            $t->json('metadata')->nullable();
            $t->timestamps();
            });
        }

        if (! Schema::hasTable('cargo_manifests')) {
            Schema::create('cargo_manifests', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('account_id')->index();
            $t->string('manifest_number', 50)->unique();
            $t->string('manifest_type', 20);           // air / sea / land
            $t->string('carrier_code', 20);
            $t->string('flight_voyage', 50)->nullable();
            $t->string('origin_code', 10);
            $t->string('destination_code', 10);
            $t->timestamp('departure_at')->nullable();
            $t->integer('total_pieces')->default(0);
            $t->decimal('total_weight_kg', 12, 2)->default(0);
            $t->string('status', 20)->default('draft');
            $t->json('metadata')->nullable();
            $t->timestamps();
            });
        }

        if (! Schema::hasTable('cargo_manifest_items')) {
            Schema::create('cargo_manifest_items', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('manifest_id')->index();
            $t->uuid('shipment_id')->index();
            $t->uuid('transport_document_id')->nullable();
            $t->string('document_number', 50);
            $t->integer('pieces');
            $t->decimal('weight_kg', 10, 2);
            $t->string('goods_description', 255)->nullable();
            $t->timestamps();
            $t->foreign('manifest_id')->references('id')->on('cargo_manifests')->cascadeOnDelete();
            });
        }

        // ═══════════════════════════════════════════════════════════
        // 6. AUDIT & REGULATORY RETENTION
        // ═══════════════════════════════════════════════════════════

        if (! Schema::hasTable('retention_policies')) {
            Schema::create('retention_policies', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('account_id')->index();
            $t->string('data_category', 50);           // shipments / invoices / customs / audit
            $t->integer('retention_years')->default(7);
            $t->string('legal_basis', 100)->nullable();
            $t->boolean('tamper_proof')->default(true);
            $t->boolean('auto_archive')->default(true);
            $t->date('last_archival_run')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamps();
            });
        }

        if (! Schema::hasTable('immutable_audit_log')) {
            Schema::create('immutable_audit_log', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('account_id')->index();
            $t->string('event_type', 50);
            $t->string('entity_type', 50);
            $t->uuid('entity_id')->nullable();
            $t->uuid('actor_id')->nullable();
            $t->string('actor_name', 100)->nullable();
            $t->string('actor_ip', 45)->nullable();
            $t->json('payload');
            $t->string('hash', 64);                    // SHA-256 chain hash
            $t->string('previous_hash', 64)->nullable();
            $t->timestamp('occurred_at');
            $t->timestamps();
            $t->index(['entity_type', 'entity_id']);
            $t->index('occurred_at');
            });
        }

        // ═══════════════════════════════════════════════════════════
        // 7. CUSTOMER SELF-SERVICE PORTAL (enhancements)
        // ═══════════════════════════════════════════════════════════

        if (! Schema::hasTable('customer_api_keys')) {
            Schema::create('customer_api_keys', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('account_id')->index();
            $t->uuid('user_id')->index();
            $t->string('name', 100);
            $t->string('key_hash', 64)->unique();
            $t->string('key_prefix', 12);
            $t->json('permissions');                    // ['shipments.create', 'tracking.read']
            $t->integer('rate_limit_per_minute')->default(60);
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            });
        }

        if (! Schema::hasTable('saved_quotes')) {
            Schema::create('saved_quotes', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('account_id')->index();
            $t->uuid('user_id');
            $t->string('origin_code', 10);
            $t->string('destination_code', 10);
            $t->string('shipment_type', 20);
            $t->decimal('weight_kg', 10, 2);
            $t->decimal('volume_cbm', 10, 2)->nullable();
            $t->json('dimensions')->nullable();
            $t->json('rates');                          // carrier options + prices
            $t->uuid('selected_rate_id')->nullable();
            $t->decimal('quoted_price', 14, 2)->nullable();
            $t->string('currency', 3)->default('SAR');
            $t->timestamp('valid_until');
            $t->string('status', 20)->default('active'); // active / expired / converted
            $t->uuid('converted_shipment_id')->nullable();
            $t->timestamps();
            });
        }

        // ═══════════════════════════════════════════════════════════
        // 8. DATA INTELLIGENCE LAYER
        // ═══════════════════════════════════════════════════════════

        if (! Schema::hasTable('analytics_snapshots')) {
            Schema::create('analytics_snapshots', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('account_id')->index();
            $t->string('metric_type', 50);             // route_profitability / branch_performance / sla_breach / clv
            $t->string('dimension', 50)->nullable();   // branch_id / route / customer_id / region
            $t->string('dimension_value', 100)->nullable();
            $t->date('period_date');
            $t->string('period_type', 10);             // daily / weekly / monthly
            $t->decimal('value', 18, 4);
            $t->json('breakdown')->nullable();
            $t->timestamps();
            $t->index(['metric_type', 'period_date']);
            $t->index(['dimension', 'dimension_value']);
            });
        }

        if (! Schema::hasTable('sla_metrics')) {
            Schema::create('sla_metrics', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('account_id')->index();
            $t->uuid('shipment_id')->nullable()->index();
            $t->string('sla_type', 30);               // pickup / transit / delivery / customs_clearance
            $t->string('region', 50)->nullable();
            $t->string('carrier_code', 20)->nullable();
            $t->integer('promised_hours');
            $t->integer('actual_hours')->nullable();
            $t->boolean('breached')->default(false);
            $t->integer('breach_hours')->default(0);
            $t->string('root_cause', 100)->nullable();
            $t->timestamps();
            $t->index(['sla_type', 'breached']);
            });
        }

        if (! Schema::hasTable('customer_lifetime_values')) {
            Schema::create('customer_lifetime_values', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('account_id')->index();
            $t->uuid('customer_id')->unique();
            $t->integer('total_shipments')->default(0);
            $t->decimal('total_revenue', 14, 2)->default(0);
            $t->decimal('total_profit', 14, 2)->default(0);
            $t->decimal('avg_order_value', 10, 2)->default(0);
            $t->decimal('lifetime_value', 14, 2)->default(0);
            $t->integer('months_active')->default(0);
            $t->date('first_shipment_date')->nullable();
            $t->date('last_shipment_date')->nullable();
            $t->string('segment', 20)->default('bronze'); // bronze / silver / gold / platinum / vip
            $t->decimal('churn_probability', 5, 4)->default(0);
            $t->json('metrics')->nullable();
            $t->timestamps();
            });
        }

        if (! Schema::hasTable('delay_predictions')) {
            Schema::create('delay_predictions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('account_id')->index();
            $t->uuid('shipment_id')->index();
            $t->decimal('delay_probability', 5, 4);   // 0.0000–1.0000
            $t->integer('predicted_delay_hours')->nullable();
            $t->json('risk_factors');                   // weather / customs / carrier / capacity
            $t->string('prediction_model', 50)->default('v1');
            $t->boolean('was_accurate')->nullable();
            $t->integer('actual_delay_hours')->nullable();
            $t->timestamps();
            });
        }

        if (! Schema::hasTable('fraud_signals')) {
            Schema::create('fraud_signals', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('account_id')->index();
            $t->uuid('entity_id');
            $t->string('entity_type', 50);             // shipment / payment / claim / account
            $t->string('signal_type', 50);             // duplicate / value_anomaly / velocity / geo_mismatch
            $t->decimal('confidence', 5, 4);           // 0.0000–1.0000
            $t->text('description');
            $t->json('evidence');
            $t->string('status', 20)->default('flagged'); // flagged / investigating / confirmed / dismissed
            $t->uuid('reviewed_by')->nullable();
            $t->timestamps();
            $t->index(['entity_type', 'entity_id']);
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'fraud_signals', 'delay_predictions', 'customer_lifetime_values',
            'sla_metrics', 'analytics_snapshots', 'saved_quotes', 'customer_api_keys',
            'immutable_audit_log', 'retention_policies', 'cargo_manifest_items',
            'cargo_manifests', 'transport_documents', 'currency_transactions',
            'exchange_rates', 'branch_pnl', 'shipment_costs',
            'capacity_bookings', 'capacity_pools', 'route_cost_factors',
            'route_legs', 'route_plans',
        ];
        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }
    }
};
