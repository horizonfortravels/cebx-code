<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Branches ──
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('city');
            $table->string('region')->nullable();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('manager_name')->nullable();
            $table->string('manager_email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('employees_count')->default(0);
            $table->timestamps();
        });

        // ── Companies (carriers, agents, partners) ──
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->enum('type', ['carrier', 'agent', 'partner'])->default('carrier');
            $table->string('country')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->decimal('rating', 3, 1)->default(0);
            $table->integer('shipments_count')->default(0);
            $table->timestamps();
        });

        // ── Vessels ──
        Schema::create('vessels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('imo_number')->unique();
            $table->string('type')->default('Container Ship');
            $table->integer('capacity_teu')->default(0);
            $table->string('flag')->nullable();
            $table->string('owner_company')->nullable();
            $table->string('current_location')->nullable();
            $table->string('status')->default('idle');
            $table->timestamps();
        });

        // ── Containers ──
        Schema::create('containers', function (Blueprint $table) {
            $table->id();
            $table->string('container_number')->unique();
            $table->string('type')->default('Standard');
            $table->string('size')->default('40ft');
            $table->foreignId('vessel_id')->nullable()->constrained()->nullOnDelete();
            $table->string('origin_port')->nullable();
            $table->string('destination_port')->nullable();
            $table->string('status')->default('available');
            $table->timestamps();
        });

        // ── Schedules ──
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->string('voyage_number')->unique();
            $table->foreignId('vessel_id')->nullable()->constrained()->nullOnDelete();
            $table->string('origin_port');
            $table->string('destination_port');
            $table->timestamp('departure_date')->nullable();
            $table->timestamp('arrival_date')->nullable();
            $table->string('status')->default('scheduled');
            $table->timestamps();
        });

        // ── Customs ──
        Schema::create('customs_declarations', function (Blueprint $table) {
            $table->id();
            $table->string('declaration_number')->unique();
            $table->foreignId('shipment_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['import', 'export'])->default('import');
            $table->string('hs_code')->nullable();
            $table->decimal('declared_value', 12, 2)->default(0);
            $table->decimal('duty_amount', 12, 2)->default(0);
            $table->string('port_name')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        // ── Drivers ──
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone');
            $table->string('employee_id')->nullable();
            $table->string('license_number');
            $table->string('vehicle_plate')->nullable();
            $table->string('region')->nullable();
            $table->string('status')->default('available');
            $table->decimal('rating', 3, 1)->default(0);
            $table->integer('deliveries_count')->default(0);
            $table->timestamps();
        });

        // ── Claims ──
        Schema::create('claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shipment_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['damage', 'loss', 'delay', 'overcharge'])->default('damage');
            $table->decimal('amount', 12, 2)->default(0);
            $table->text('description')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        // ── HS Codes ──
        Schema::create('hs_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('description_ar');
            $table->string('description_en')->nullable();
            $table->integer('chapter')->nullable();
            $table->decimal('duty_rate', 5, 2)->default(5);
            $table->boolean('is_restricted')->default(false);
            $table->boolean('requires_license')->default(false);
            $table->timestamps();
        });

        // ── KYC Requests ──
        Schema::create('kyc_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['individual', 'company'])->default('company');
            $table->string('status')->default('pending');
            $table->integer('documents_count')->default(0);
            $table->foreignId('reviewer_id')->nullable();
            $table->timestamps();
        });

        // ── DG Classifications ──
        Schema::create('dg_classifications', function (Blueprint $table) {
            $table->id();
            $table->integer('class_number');
            $table->string('division')->nullable();
            $table->string('description');
            $table->string('un_number')->nullable();
            $table->string('packing_group')->nullable();
            $table->text('restrictions')->nullable();
            $table->boolean('is_allowed')->default(true);
            $table->timestamps();
        });

        // ── Pricing Rules ──
        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->string('carrier_code');
            $table->string('carrier_name');
            $table->enum('service_type', ['domestic', 'international'])->default('domestic');
            $table->string('zone_name')->nullable();
            $table->decimal('base_weight', 8, 2)->default(1);
            $table->decimal('base_price', 10, 2)->default(0);
            $table->decimal('extra_kg_price', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ── Risk Rules ──
        Schema::create('risk_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('condition_description')->nullable();
            $table->enum('risk_level', ['low', 'medium', 'high'])->default('medium');
            $table->string('action_description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ── Risk Alerts ──
        Schema::create('risk_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('risk_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('level', ['low', 'medium', 'high'])->default('medium');
            $table->timestamps();
        });

        // ── Audit Logs ──
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event');
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $tables = ['audit_logs','risk_alerts','risk_rules','pricing_rules','dg_classifications',
            'kyc_requests','hs_codes','claims','drivers','customs_declarations',
            'schedules','containers','vessels','companies','branches'];
        foreach ($tables as $t) Schema::dropIfExists($t);
    }
};
