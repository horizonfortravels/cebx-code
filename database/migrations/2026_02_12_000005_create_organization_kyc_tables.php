<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('organization_profiles')) {
            return;
        }

        // ── Organization Profile (created automatically for org accounts) ──
        Schema::create('organization_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('account_id')->unique();
            $table->string('legal_name', 200);
            $table->string('trade_name', 200)->nullable();
            $table->string('registration_number', 100)->nullable()->comment('السجل التجاري');
            $table->string('tax_id', 100)->nullable()->comment('الرقم الضريبي');
            $table->string('industry', 100)->nullable();
            $table->string('company_size', 50)->nullable()->comment('small, medium, large, enterprise');

            // Address
            $table->string('country', 3)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('address_line_1', 255)->nullable();
            $table->string('address_line_2', 255)->nullable();
            $table->string('postal_code', 20)->nullable();

            // Contact
            $table->string('phone', 20)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('website', 255)->nullable();

            // Billing
            $table->string('billing_currency', 3)->default('SAR');
            $table->string('billing_cycle', 20)->default('monthly')->comment('monthly, weekly, per-shipment');
            $table->string('billing_email', 255)->nullable();

            // Logo
            $table->string('logo_path', 500)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('account_id');
            // FK omitted: accounts.id may be bigint (0001 migration) or uuid (2026 migration)
        });

        // ── KYC Verification Status ──────────────────────────────
        Schema::create('kyc_verifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('account_id');
            $table->string('status', 30)->default('unverified')
                  ->comment('unverified, pending, approved, rejected, expired');
            $table->string('verification_type', 50)->comment('individual, organization');
            $table->json('required_documents')->nullable()->comment('List of required document types');
            $table->json('submitted_documents')->nullable()->comment('List of submitted document references');
            $table->text('rejection_reason')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'status']);
            $table->index('reviewed_by');
            // FKs omitted: accounts.id and users.id may be bigint or uuid
        });

        // ── Add KYC status to accounts for quick lookup ──────────
        if (Schema::hasTable('accounts') && ! Schema::hasColumn('accounts', 'kyc_status')) {
            Schema::table('accounts', function (Blueprint $table) {
                $table->string('kyc_status', 30)->default('unverified')->after('status');
            });
        }
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('kyc_status');
        });
        Schema::dropIfExists('kyc_verifications');
        Schema::dropIfExists('organization_profiles');
    }
};
