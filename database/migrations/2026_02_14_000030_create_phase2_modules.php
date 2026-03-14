<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CBEX GROUP — New Modules Migration (Phase 2)
 *
 * BRN: Branches & Companies
 * CNT: Containers & Vessels
 * CUS: Customs & Clearance
 * HSC: HS Codes
 * TRF: Tariff Engine
 * CLM: Claims & Insurance
 * DRV: Drivers & Last Mile
 * INC: Incoterms
 * AIR: AI & Risk Scoring
 */
return new class extends Migration
{
    public function up(): void
    {
        // ══════════════════════════════════════════════════════════════
        // BRN-001: COMPANIES
        // ══════════════════════════════════════════════════════════════
        if (! Schema::hasTable('companies')) {
            Schema::create('companies', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->uuid('account_id');
                $t->string('name', 200);
                $t->string('legal_name', 300)->nullable();
                $t->string('registration_number', 100)->nullable();
                $t->string('tax_id', 100)->nullable();
                $t->string('country', 3);
                $t->string('base_currency', 3)->default('SAR');
                $t->string('timezone', 50)->default('Asia/Riyadh');
                $t->string('industry', 100)->nullable();
                $t->enum('status', ['active', 'suspended', 'inactive'])->default('active');
                $t->string('logo_url', 500)->nullable();
                $t->string('website', 300)->nullable();
                $t->string('phone', 30)->nullable();
                $t->string('email', 255)->nullable();
                $t->text('address')->nullable();
                $t->json('settings')->nullable();
                $t->json('metadata')->nullable();
                $t->timestamps();
                $t->softDeletes();
                $t->index(['account_id', 'status']);
            });
        }

        // ══════════════════════════════════════════════════════════════
        // BRN-002: BRANCHES
        // ══════════════════════════════════════════════════════════════
        if (! Schema::hasTable('branches')) {
            Schema::create('branches', function (Blueprint $t) {
                $t->engine = 'InnoDB';
                $t->uuid('id')->primary();
                $t->uuid('account_id');
                $t->uuid('company_id');
                $t->string('name', 200);
                $t->string('code', 20)->unique();
                $t->string('country', 3);
                $t->string('city', 100);
                $t->string('state', 100)->nullable();
                $t->string('postal_code', 20)->nullable();
                $t->text('address')->nullable();
                $t->enum('branch_type', ['headquarters', 'hub', 'port', 'airport', 'office', 'warehouse', 'customs_office'])->default('office');
                $t->string('phone', 30)->nullable();
                $t->string('email', 255)->nullable();
                $t->string('manager_name', 200)->nullable();
                $t->uuid('manager_user_id')->nullable();
                $t->decimal('latitude', 10, 7)->nullable();
                $t->decimal('longitude', 10, 7)->nullable();
                $t->enum('status', ['active', 'inactive', 'maintenance'])->default('active');
                $t->json('operating_hours')->nullable();
                $t->json('capabilities')->nullable()->comment('["air","sea","land","customs"]');
                $t->json('metadata')->nullable();
                $t->timestamps();
                $t->softDeletes();
                $t->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
                $t->index(['account_id', 'branch_type', 'status']);
                $t->index(['country', 'city']);
            });
        }

        $this->ensureInnoDbTable('branches');
        $branchIdType = $this->resolveBranchIdType();

        // Branch Staff
        if (! Schema::hasTable('branch_staff')) {
            Schema::create('branch_staff', function (Blueprint $t) use ($branchIdType) {
                $t->engine = 'InnoDB';
                $t->uuid('id')->primary();
                if ($branchIdType === 'unsigned_bigint') {
                    $t->unsignedBigInteger('branch_id');
                } else {
                    $t->uuid('branch_id');
                }
                $t->uuid('user_id');
                $t->string('role', 50)->default('agent');
                $t->date('assigned_at');
                $t->date('released_at')->nullable();
                $t->boolean('is_primary')->default(false);
                $t->timestamps();
                $t->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
                $t->index('user_id');
                $t->unique(['branch_id', 'user_id']);
            });
        }
        $this->ensureInnoDbTable('branch_staff');

        // ══════════════════════════════════════════════════════════════
        // INC-001: INCOTERMS
        // ══════════════════════════════════════════════════════════════
        if (! Schema::hasTable('incoterms')) {
            Schema::create('incoterms', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->string('code', 3)->unique()->comment('EXW, FOB, CIF, DDP...');
                $t->string('name', 100);
                $t->string('name_ar', 100)->nullable();
                $t->text('description')->nullable();
                $t->text('description_ar')->nullable();
                $t->enum('transport_mode', ['any', 'sea_inland'])->default('any');
                $t->boolean('seller_pays_freight')->default(false);
                $t->boolean('seller_pays_insurance')->default(false);
                $t->boolean('seller_pays_import_duty')->default(false);
                $t->boolean('seller_handles_export_clearance')->default(true);
                $t->boolean('buyer_handles_import_clearance')->default(true);
                $t->string('risk_transfer_point', 200)->nullable();
                $t->integer('sort_order')->default(0);
                $t->boolean('is_active')->default(true);
                $t->timestamps();
            });
        }

        // ══════════════════════════════════════════════════════════════
        // HSC-001: HS CODES REGISTRY
        // ══════════════════════════════════════════════════════════════
        if (! Schema::hasTable('hs_codes')) {
            Schema::create('hs_codes', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->string('code', 12)->comment('e.g. 6109.10.00');
                $t->string('chapter', 2)->comment('First 2 digits');
                $t->string('heading', 4)->comment('First 4 digits');
                $t->string('subheading', 6)->comment('First 6 digits');
                $t->string('description', 500);
                $t->string('description_ar', 500)->nullable();
                $t->string('country', 3)->default('*')->comment('* = global');
                $t->decimal('duty_rate', 8, 4)->default(0)->comment('Percentage');
                $t->decimal('vat_rate', 8, 4)->default(15)->comment('Percentage');
                $t->decimal('excise_rate', 8, 4)->default(0);
                $t->boolean('is_restricted')->default(false);
                $t->boolean('is_prohibited')->default(false);
                $t->boolean('requires_license')->default(false);
                $t->boolean('is_dangerous_goods')->default(false);
                $t->string('restriction_notes', 500)->nullable();
                $t->string('unit_of_measure', 20)->default('KG');
                $t->boolean('is_active')->default(true);
                $t->timestamps();
                $t->index(['code', 'country']);
                $t->index('chapter');
                $t->index('is_restricted');
            });
        }

        // ══════════════════════════════════════════════════════════════
        // CNT-001: VESSELS & SCHEDULES
        // ══════════════════════════════════════════════════════════════
        if (! Schema::hasTable('vessels')) {
            Schema::create('vessels', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->uuid('account_id');
                $t->string('vessel_name', 200);
                $t->string('imo_number', 20)->nullable()->comment('IMO ship ID');
                $t->string('mmsi', 20)->nullable();
                $t->string('call_sign', 20)->nullable();
                $t->string('flag', 3)->nullable();
                $t->enum('vessel_type', ['container', 'bulk', 'tanker', 'roro', 'general'])->default('container');
                $t->string('operator', 200)->nullable();
                $t->integer('capacity_teu')->nullable()->comment('Twenty-foot Equivalent Units');
                $t->decimal('max_deadweight', 12, 2)->nullable();
                $t->enum('status', ['active', 'in_port', 'at_sea', 'maintenance', 'decommissioned'])->default('active');
                $t->json('metadata')->nullable();
                $t->timestamps();
                $t->softDeletes();
                $t->index('account_id');
            });
        }

        if (! Schema::hasTable('vessel_schedules')) {
            Schema::create('vessel_schedules', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->uuid('account_id');
                $t->uuid('vessel_id');
                $t->string('voyage_number', 50);
                $t->string('service_route', 100)->nullable();
                $t->string('port_of_loading', 5)->comment('UN/LOCODE');
                $t->string('port_of_loading_name', 200)->nullable();
                $t->string('port_of_discharge', 5);
                $t->string('port_of_discharge_name', 200)->nullable();
                $t->dateTime('etd')->comment('Estimated Time of Departure');
                $t->dateTime('eta')->comment('Estimated Time of Arrival');
                $t->dateTime('atd')->nullable()->comment('Actual Time of Departure');
                $t->dateTime('ata')->nullable()->comment('Actual Time of Arrival');
                $t->dateTime('cut_off_date')->nullable();
                $t->integer('transit_days')->nullable();
                $t->enum('status', ['scheduled', 'departed', 'in_transit', 'arrived', 'cancelled'])->default('scheduled');
                $t->json('port_calls')->nullable()->comment('Intermediate ports');
                $t->json('metadata')->nullable();
                $t->timestamps();
                $t->index('vessel_id');
                $t->index('account_id');
                $t->index(['port_of_loading', 'port_of_discharge', 'etd']);
            });
        }

        // ══════════════════════════════════════════════════════════════
        // CNT-002: CONTAINERS
        // ══════════════════════════════════════════════════════════════
        if (! Schema::hasTable('containers')) {
            Schema::create('containers', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->uuid('account_id');
                $t->uuid('vessel_schedule_id')->nullable();
                $t->string('container_number', 15)->comment('BIC format: CSQU3054383');
                $t->enum('size', ['20ft', '40ft', '40ft_hc', '45ft'])->default('20ft');
                $t->enum('type', ['dry', 'reefer', 'open_top', 'flat_rack', 'tank', 'special'])->default('dry');
                $t->string('seal_number', 50)->nullable();
                $t->decimal('tare_weight', 10, 2)->nullable()->comment('Empty weight kg');
                $t->decimal('max_payload', 10, 2)->nullable();
                $t->decimal('current_weight', 10, 2)->nullable();
                $t->decimal('temperature_min', 5, 1)->nullable()->comment('Reefer only');
                $t->decimal('temperature_max', 5, 1)->nullable();
                $t->string('location', 200)->nullable();
                $t->enum('status', ['empty', 'loading', 'loaded', 'in_transit', 'at_port', 'delivered', 'returned'])->default('empty');
                $t->uuid('origin_branch_id')->nullable();
                $t->uuid('destination_branch_id')->nullable();
                $t->json('metadata')->nullable();
                $t->timestamps();
                $t->softDeletes();
                $t->foreign('vessel_schedule_id')->references('id')->on('vessel_schedules')->nullOnDelete();
                $t->index(['container_number']);
                $t->index(['account_id', 'status']);
            });
        }

        // Container ↔ Shipment (M:N)
        if (! Schema::hasTable('container_shipments')) {
            Schema::create('container_shipments', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->uuid('container_id');
                $t->uuid('shipment_id');
                $t->integer('packages_count')->default(1);
                $t->decimal('weight', 10, 3)->nullable();
                $t->decimal('volume_cbm', 10, 4)->nullable();
                $t->string('loading_position', 50)->nullable();
                $t->timestamp('loaded_at')->nullable();
                $t->timestamp('unloaded_at')->nullable();
                $t->timestamps();
                $t->index('container_id');
                $t->index('shipment_id');
                $t->unique(['container_id', 'shipment_id']);
            });
        }

        // ══════════════════════════════════════════════════════════════
        // CUS-001: CUSTOMS BROKERS
        // ══════════════════════════════════════════════════════════════
        if (! Schema::hasTable('customs_brokers')) {
            Schema::create('customs_brokers', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->uuid('account_id');
                $t->string('name', 200);
                $t->string('license_number', 100);
                $t->string('country', 3);
                $t->string('city', 100)->nullable();
                $t->string('phone', 30)->nullable();
                $t->string('email', 255)->nullable();
                $t->string('company_name', 200)->nullable();
                $t->decimal('commission_rate', 5, 2)->default(0)->comment('Percentage');
                $t->decimal('fixed_fee', 10, 2)->default(0);
                $t->string('currency', 3)->default('SAR');
                $t->enum('status', ['active', 'suspended', 'inactive'])->default('active');
                $t->decimal('rating', 3, 2)->default(5.0);
                $t->integer('total_clearances')->default(0);
                $t->json('specializations')->nullable()->comment('["air","sea","land"]');
                $t->json('metadata')->nullable();
                $t->timestamps();
                $t->softDeletes();
                $t->index(['account_id', 'country', 'status']);
            });
        }

        // ══════════════════════════════════════════════════════════════
        // CUS-002: CUSTOMS DECLARATIONS
        // ══════════════════════════════════════════════════════════════
        if (! Schema::hasTable('customs_declarations')) {
            Schema::create('customs_declarations', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->uuid('account_id');
                $t->uuid('shipment_id');
                $t->uuid('broker_id')->nullable();
            $t->uuid('branch_id')->nullable()->comment('Customs office branch');
            $t->string('declaration_number', 50)->nullable()->unique();
            $t->enum('declaration_type', ['export', 'import', 'transit', 're_export'])->default('import');
            $t->string('customs_office', 200)->nullable();
            $t->string('origin_country', 3);
            $t->string('destination_country', 3);
            $t->string('incoterm_code', 3)->nullable();

            // Status Machine
            $t->enum('customs_status', [
                'draft',
                'documents_pending',
                'submitted',
                'under_review',
                'inspection_required',
                'inspecting',
                'duty_assessment',
                'payment_pending',
                'duty_paid',
                'cleared',
                'held',
                'rejected',
                'cancelled',
            ])->default('draft');

            // Financial
            $t->decimal('declared_value', 14, 2)->default(0);
            $t->string('declared_currency', 3)->default('SAR');
            $t->decimal('duty_amount', 14, 2)->default(0);
            $t->decimal('vat_amount', 14, 2)->default(0);
            $t->decimal('excise_amount', 14, 2)->default(0);
            $t->decimal('other_fees', 14, 2)->default(0);
            $t->decimal('total_customs_charges', 14, 2)->default(0);
            $t->decimal('broker_fee', 10, 2)->default(0);

            // Inspection
            $t->boolean('inspection_flag')->default(false);
            $t->dateTime('inspection_date')->nullable();
            $t->string('inspection_result', 200)->nullable();
            $t->text('inspection_notes')->nullable();
            $t->uuid('inspector_user_id')->nullable();

            // Dates
            $t->dateTime('submitted_at')->nullable();
            $t->dateTime('cleared_at')->nullable();
            $t->dateTime('duty_paid_at')->nullable();
            $t->string('duty_payment_ref', 100)->nullable();

                $t->text('notes')->nullable();
                $t->json('metadata')->nullable();
                $t->timestamps();
                $t->softDeletes();

                $t->foreign('broker_id')->references('id')->on('customs_brokers')->nullOnDelete();
                $t->index(['account_id', 'customs_status']);
                $t->index(['shipment_id']);
            });
        }

        // ══════════════════════════════════════════════════════════════
        // CUS-003: REQUIRED DOCUMENTS
        // ══════════════════════════════════════════════════════════════
        if (! Schema::hasTable('customs_documents')) {
            Schema::create('customs_documents', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->uuid('declaration_id');
                $t->uuid('shipment_id');
            $t->enum('document_type', [
                'commercial_invoice',
                'packing_list',
                'bill_of_lading',
                'airway_bill',
                'certificate_of_origin',
                'insurance_certificate',
                'phytosanitary_certificate',
                'health_certificate',
                'import_license',
                'export_license',
                'customs_form',
                'power_of_attorney',
                'saso_certificate',     // Saudi Standards
                'saber_certificate',    // Saudi SABER
                'other',
            ]);
            $t->string('document_name', 200);
            $t->string('document_number', 100)->nullable();
            $t->string('file_path', 500)->nullable();
            $t->string('file_type', 10)->nullable();
            $t->integer('file_size')->nullable();
            $t->uuid('uploaded_by')->nullable();
            $t->boolean('is_required')->default(true);
            $t->boolean('is_verified')->default(false);
            $t->uuid('verified_by')->nullable();
            $t->dateTime('verified_at')->nullable();
            $t->string('rejection_reason', 500)->nullable();
            $t->dateTime('expiry_date')->nullable();
                $t->json('metadata')->nullable();
                $t->timestamps();
                $t->index('declaration_id');
                $t->index('shipment_id');
            });
        }

        // ══════════════════════════════════════════════════════════════
        // CUS-004: SHIPMENT ITEMS (HS Code linked)
        // ══════════════════════════════════════════════════════════════
        if (! Schema::hasTable('shipment_items')) {
            Schema::create('shipment_items', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->uuid('shipment_id');
            $t->uuid('declaration_id')->nullable();
            $t->string('description', 500);
            $t->string('description_ar', 500)->nullable();
            $t->string('hs_code', 12)->nullable();
            $t->integer('quantity')->default(1);
            $t->string('unit', 20)->default('PCS');
            $t->decimal('weight', 10, 3)->nullable();
            $t->decimal('unit_value', 12, 2)->default(0);
            $t->decimal('total_value', 14, 2)->default(0);
            $t->string('currency', 3)->default('SAR');
            $t->string('origin_country', 3)->nullable();
            $t->boolean('dangerous_flag')->default(false);
            $t->string('dg_class', 10)->nullable();
            $t->string('un_number', 10)->nullable();
            $t->string('brand', 100)->nullable();
            $t->string('model', 100)->nullable();
            $t->string('serial_number', 100)->nullable();
                $t->json('metadata')->nullable();
                $t->timestamps();
                $t->index(['shipment_id']);
                $t->index(['hs_code']);
            });
        }

        // ══════════════════════════════════════════════════════════════
        // TRF-001: TARIFF RULES (International)
        // ══════════════════════════════════════════════════════════════
        if (! Schema::hasTable('tariff_rules')) {
            Schema::create('tariff_rules', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->uuid('account_id');
            $t->string('name', 200);
            $t->string('origin_country', 3)->default('*');
            $t->string('destination_country', 3)->default('*');
            $t->string('origin_city', 100)->nullable();
            $t->string('destination_city', 100)->nullable();
            $t->enum('shipment_type', ['air', 'sea', 'land', 'express', 'any'])->default('any');
            $t->string('carrier_code', 50)->nullable();
            $t->string('service_level', 50)->nullable();
            $t->string('incoterm_code', 3)->nullable();
            $t->decimal('min_weight', 10, 3)->default(0);
            $t->decimal('max_weight', 10, 3)->default(999999);
            $t->decimal('min_volume', 10, 4)->nullable();
            $t->decimal('max_volume', 10, 4)->nullable();
            $t->enum('pricing_unit', ['kg', 'cbm', 'piece', 'container', 'flat'])->default('kg');
            $t->decimal('base_price', 12, 4)->default(0);
            $t->decimal('price_per_unit', 12, 4)->default(0);
            $t->decimal('minimum_charge', 12, 2)->default(0);
            $t->decimal('fuel_surcharge_percent', 6, 2)->default(0);
            $t->decimal('security_surcharge', 10, 2)->default(0);
            $t->decimal('peak_season_surcharge', 6, 2)->default(0);
            $t->decimal('insurance_rate', 6, 4)->default(0);
            $t->string('currency', 3)->default('SAR');
            $t->date('valid_from');
            $t->date('valid_to')->nullable();
            $t->boolean('is_active')->default(true);
            $t->integer('priority')->default(0);
            $t->json('conditions')->nullable();
                $t->json('metadata')->nullable();
                $t->timestamps();
                $t->softDeletes();
                $t->index('account_id');
                $t->index(['is_active', 'valid_from', 'valid_to']);
            });
        }

        // Shipment Charges (detailed)
        if (! Schema::hasTable('shipment_charges')) {
            Schema::create('shipment_charges', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->uuid('shipment_id');
            $t->uuid('tariff_rule_id')->nullable();
            $t->enum('charge_type', [
                'freight', 'fuel_surcharge', 'security_surcharge', 'insurance',
                'customs_duty', 'vat', 'handling', 'storage', 'documentation',
                'pickup', 'delivery', 'packaging', 'dangerous_goods',
                'oversize', 'overweight', 'weekend_delivery', 'cod_fee',
                'broker_fee', 'inspection_fee', 'demurrage', 'detention',
                'port_charges', 'terminal_handling', 'inland_transport',
                'peak_season', 'discount', 'adjustment', 'other',
            ]);
            $t->string('description', 300)->nullable();
            $t->decimal('amount', 14, 2);
            $t->string('currency', 3)->default('SAR');
            $t->decimal('exchange_rate', 12, 6)->default(1);
            $t->decimal('amount_base', 14, 2)->nullable()->comment('Amount in base currency');
            $t->boolean('is_billable')->default(true);
            $t->boolean('is_taxable')->default(true);
            $t->uuid('created_by')->nullable();
                $t->json('metadata')->nullable();
                $t->timestamps();
                $t->index(['shipment_id', 'charge_type']);
            });
        }

        // ══════════════════════════════════════════════════════════════
        // CLM-001: CLAIMS & INSURANCE
        // ══════════════════════════════════════════════════════════════
        if (! Schema::hasTable('claims')) {
            Schema::create('claims', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->uuid('account_id');
                $t->uuid('shipment_id');
            $t->string('claim_number', 30)->unique();
            $t->enum('claim_type', [
                'damage', 'loss', 'shortage', 'delay',
                'wrong_delivery', 'theft', 'water_damage',
                'temperature_deviation', 'other',
            ]);
            $t->enum('status', [
                'draft', 'submitted', 'under_review', 'investigation',
                'assessment', 'approved', 'partially_approved',
                'rejected', 'settled', 'closed', 'appealed',
            ])->default('draft');
            $t->text('description');
            $t->decimal('claimed_amount', 14, 2);
            $t->string('claimed_currency', 3)->default('SAR');
            $t->decimal('approved_amount', 14, 2)->nullable();
            $t->decimal('settled_amount', 14, 2)->nullable();
            $t->string('settlement_currency', 3)->nullable();
            $t->string('settlement_ref', 100)->nullable();
            $t->date('incident_date');
            $t->string('incident_location', 300)->nullable();
            $t->uuid('filed_by')->comment('User who filed');
            $t->uuid('assigned_to')->nullable()->comment('Claims handler');
            $t->uuid('approved_by')->nullable();
            $t->text('resolution_notes')->nullable();
            $t->text('rejection_reason')->nullable();
            $t->dateTime('submitted_at')->nullable();
            $t->dateTime('resolved_at')->nullable();
            $t->dateTime('settled_at')->nullable();
            $t->date('sla_deadline')->nullable();
                $t->json('metadata')->nullable();
                $t->timestamps();
                $t->softDeletes();
                $t->index(['account_id', 'status']);
                $t->index(['shipment_id']);
            });
        }

        // Claim Evidence/Documents
        if (! Schema::hasTable('claim_documents')) {
            Schema::create('claim_documents', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('claim_id');
            $t->enum('document_type', ['photo', 'video', 'invoice', 'receipt', 'report', 'correspondence', 'other']);
            $t->string('title', 200);
            $t->string('file_path', 500);
            $t->string('file_type', 10);
            $t->integer('file_size')->default(0);
            $t->uuid('uploaded_by');
                $t->text('notes')->nullable();
                $t->timestamps();
                $t->index('claim_id');
            });
        }

        // Claim History
        if (! Schema::hasTable('claim_history')) {
            Schema::create('claim_history', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->uuid('claim_id');
                $t->string('from_status', 30);
                $t->string('to_status', 30);
                $t->uuid('changed_by');
                $t->text('notes')->nullable();
                $t->timestamps();
                $t->index('claim_id');
                $t->index('changed_by');
            });
        }

        // ══════════════════════════════════════════════════════════════
        // DRV-001: DRIVERS
        // ══════════════════════════════════════════════════════════════
        if (! Schema::hasTable('drivers')) {
            Schema::create('drivers', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->uuid('account_id');
                $t->uuid('branch_id')->nullable();
            $t->string('name', 200);
            $t->string('phone', 30);
            $t->string('email', 255)->nullable();
            $t->string('license_number', 50);
            $t->date('license_expiry');
            $t->string('vehicle_type', 50)->nullable();
            $t->string('vehicle_plate', 30)->nullable();
            $t->string('id_number', 30)->nullable();
            $t->string('nationality', 3)->nullable();
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->dateTime('location_updated_at')->nullable();
            $t->enum('status', ['available', 'on_duty', 'on_delivery', 'off_duty', 'suspended'])->default('available');
            $t->decimal('rating', 3, 2)->default(5.0);
            $t->integer('total_deliveries')->default(0);
            $t->integer('successful_deliveries')->default(0);
            $t->string('photo_url', 500)->nullable();
            $t->json('zones')->nullable()->comment('Delivery zones/areas');
                $t->json('metadata')->nullable();
                $t->timestamps();
                $t->softDeletes();
                $t->index(['account_id', 'status']);
            });
        }

        // ══════════════════════════════════════════════════════════════
        // DRV-002: DELIVERY ASSIGNMENTS
        // ══════════════════════════════════════════════════════════════
        if (! Schema::hasTable('delivery_assignments')) {
            Schema::create('delivery_assignments', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->uuid('account_id');
                $t->uuid('shipment_id');
                $t->uuid('driver_id');
            $t->uuid('branch_id')->nullable();
            $t->string('assignment_number', 30)->unique();
            $t->enum('type', ['pickup', 'delivery', 'return'])->default('delivery');
            $t->enum('status', [
                'assigned', 'accepted', 'rejected', 'en_route_pickup',
                'picked_up', 'en_route_delivery', 'arrived', 'attempting',
                'delivered', 'failed', 'returned', 'cancelled',
            ])->default('assigned');
            $t->integer('attempt_number')->default(1);
            $t->integer('max_attempts')->default(3);
            $t->dateTime('scheduled_at')->nullable();
            $t->dateTime('accepted_at')->nullable();
            $t->dateTime('picked_up_at')->nullable();
            $t->dateTime('delivered_at')->nullable();
            $t->string('failure_reason', 500)->nullable();
            $t->text('delivery_notes')->nullable();
            $t->text('special_instructions')->nullable();

            // Route
            $t->decimal('pickup_lat', 10, 7)->nullable();
            $t->decimal('pickup_lng', 10, 7)->nullable();
            $t->decimal('delivery_lat', 10, 7)->nullable();
            $t->decimal('delivery_lng', 10, 7)->nullable();
            $t->decimal('distance_km', 8, 2)->nullable();
            $t->integer('estimated_minutes')->nullable();

                $t->json('metadata')->nullable();
                $t->timestamps();
                $t->index('driver_id');
                $t->index(['driver_id', 'status']);
                $t->index(['shipment_id']);
            });
        }

        // ══════════════════════════════════════════════════════════════
        // DRV-003: PROOF OF DELIVERY
        // ══════════════════════════════════════════════════════════════
        if (! Schema::hasTable('proof_of_deliveries')) {
            Schema::create('proof_of_deliveries', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->uuid('assignment_id');
                $t->uuid('shipment_id');
            $t->enum('pod_type', ['signature', 'otp', 'photo', 'pin', 'biometric']);
            $t->string('recipient_name', 200)->nullable();
            $t->string('recipient_relation', 100)->nullable()->comment('self/family/colleague/security');
            $t->string('recipient_id_number', 30)->nullable();

            // Signature
            $t->text('signature_data')->nullable()->comment('Base64 encoded');

            // OTP
            $t->string('otp_code', 10)->nullable();
            $t->boolean('otp_verified')->default(false);

            // Photo
            $t->string('photo_url', 500)->nullable();
            $t->string('photo_thumbnail', 500)->nullable();

            // Location at delivery
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->dateTime('captured_at');

                $t->text('notes')->nullable();
                $t->json('metadata')->nullable();
                $t->timestamps();
                $t->index('assignment_id');
                $t->index('shipment_id');
            });
        }

        // ══════════════════════════════════════════════════════════════
        // AIR-001: RISK SCORES
        // ══════════════════════════════════════════════════════════════
        if (! Schema::hasTable('risk_scores')) {
            Schema::create('risk_scores', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->uuid('shipment_id');
            $t->decimal('overall_score', 5, 2)->comment('0-100, higher = riskier');
            $t->decimal('delay_probability', 5, 2)->default(0)->comment('0-100%');
            $t->decimal('damage_probability', 5, 2)->default(0);
            $t->decimal('customs_risk', 5, 2)->default(0);
            $t->decimal('fraud_risk', 5, 2)->default(0);
            $t->decimal('financial_risk', 5, 2)->default(0);
            $t->enum('risk_level', ['low', 'medium', 'high', 'critical'])->default('low');
            $t->json('risk_factors')->nullable()->comment('Detailed breakdown');
            $t->json('recommendations')->nullable();
            $t->integer('predicted_transit_days')->nullable();
            $t->dateTime('predicted_delivery_at')->nullable();
                $t->string('model_version', 20)->nullable();
                $t->timestamps();
                $t->index(['shipment_id']);
                $t->index(['risk_level']);
            });
        }

        // ══════════════════════════════════════════════════════════════
        // AIR-002: ROUTE OPTIMIZATION
        // ══════════════════════════════════════════════════════════════
        if (! Schema::hasTable('route_suggestions')) {
            Schema::create('route_suggestions', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->uuid('shipment_id');
            $t->integer('rank')->default(1);
            $t->string('carrier_code', 50);
            $t->string('service_code', 50);
            $t->enum('transport_mode', ['air', 'sea', 'land', 'multimodal']);
            $t->json('route_legs')->comment('[{from,to,mode,carrier,days}]');
            $t->integer('estimated_days');
            $t->decimal('estimated_cost', 14, 2);
            $t->string('currency', 3)->default('SAR');
            $t->decimal('reliability_score', 5, 2)->comment('0-100');
            $t->decimal('carbon_footprint_kg', 10, 2)->nullable();
            $t->boolean('is_recommended')->default(false);
                $t->boolean('is_selected')->default(false);
                $t->json('metadata')->nullable();
                $t->timestamps();
                $t->index('shipment_id');
            });
        }

        // ══════════════════════════════════════════════════════════════
        // ENHANCE EXISTING: Shipments table — add new fields
        // ══════════════════════════════════════════════════════════════
        if (Schema::hasTable('shipments')) {
            Schema::table('shipments', function (Blueprint $t) {
                if (! Schema::hasColumn('shipments', 'shipment_type')) {
                    $t->enum('shipment_type', ['air', 'sea', 'land', 'express', 'multimodal'])->default('express');
                }
                if (! Schema::hasColumn('shipments', 'service_level')) {
                    $t->enum('service_level', ['express', 'standard', 'economy', 'premium', 'same_day'])->default('standard');
                }
                if (! Schema::hasColumn('shipments', 'incoterm_code')) {
                    $t->string('incoterm_code', 3)->nullable();
                }
                if (! Schema::hasColumn('shipments', 'origin_branch_id')) {
                    $t->uuid('origin_branch_id')->nullable();
                }
                if (! Schema::hasColumn('shipments', 'destination_branch_id')) {
                    $t->uuid('destination_branch_id')->nullable();
                }
                if (! Schema::hasColumn('shipments', 'company_id')) {
                    $t->uuid('company_id')->nullable();
                }
                if (! Schema::hasColumn('shipments', 'declared_value')) {
                    $t->decimal('declared_value', 14, 2)->default(0);
                }
                if (! Schema::hasColumn('shipments', 'total_volume')) {
                    $t->decimal('total_volume', 10, 4)->nullable();
                }
                if (! Schema::hasColumn('shipments', 'insurance_flag')) {
                    $t->boolean('insurance_flag')->default(false);
                }
                if (! Schema::hasColumn('shipments', 'driver_id')) {
                    $t->uuid('driver_id')->nullable();
                }
                if (! Schema::hasColumn('shipments', 'pod_status')) {
                    $t->string('pod_status', 20)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        // Drop in reverse order
        if (Schema::hasTable('shipments')) {
            Schema::table('shipments', function (Blueprint $t) {
                $cols = ['shipment_type','service_level','incoterm_code','origin_branch_id',
                         'destination_branch_id','company_id','declared_value','total_volume',
                         'insurance_flag','driver_id','pod_status'];
                foreach ($cols as $c) {
                    if (Schema::hasColumn('shipments', $c)) {
                        $t->dropColumn($c);
                    }
                }
            });
        }

        $tables = [
            'route_suggestions', 'risk_scores',
            'proof_of_deliveries', 'delivery_assignments', 'drivers',
            'claim_history', 'claim_documents', 'claims',
            'shipment_charges', 'tariff_rules',
            'shipment_items',
            'customs_documents', 'customs_declarations', 'customs_brokers',
            'container_shipments', 'containers', 'vessel_schedules', 'vessels',
            'hs_codes', 'incoterms',
            'branch_staff', 'branches', 'companies',
        ];
        foreach ($tables as $table) Schema::dropIfExists($table);
    }

    private function resolveBranchIdType(): string
    {
        if (! Schema::hasTable('branches') || ! Schema::hasColumn('branches', 'id')) {
            return 'uuid';
        }

        $type = strtolower((string) Schema::getColumnType('branches', 'id'));

        if (in_array($type, [
            'bigint',
            'biginteger',
            'int',
            'integer',
            'smallint',
            'tinyint',
            'mediumint',
        ], true)) {
            return 'unsigned_bigint';
        }

        return 'uuid';
    }

    private function ensureInnoDbTable(string $table): void
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable($table)) {
            return;
        }

        DB::statement("ALTER TABLE `{$table}` ENGINE=InnoDB");
    }
};
