<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ORG Module — Organizations & Teams
 * FR-ORG-001→010 (10 requirements)
 *
 * Tables:
 *   1. organizations        — FR-ORG-001/002: Organization profiles
 *   2. organization_members — FR-ORG-003/007: Memberships & roles
 *   3. organization_invites — FR-ORG-003: Member invitations
 *   4. organization_wallets — FR-ORG-009/010: Per-org wallet & settings
 *   5. permission_catalog   — FR-ORG-004/005: Available permissions
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('organizations')) {
            return;
        }

        // ═══════════════════════════════════════════════════════════
        // 1. organizations — FR-ORG-001/002/008
        // ═══════════════════════════════════════════════════════════
        Schema::create('organizations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('account_id');

            $table->string('legal_name', 300);
            $table->string('trade_name', 300)->nullable();
            $table->string('registration_number', 100)->nullable();  // السجل التجاري
            $table->string('tax_number', 100)->nullable();           // الرقم الضريبي
            $table->string('country_code', 2)->default('SA');

            // ── Billing & Contact ────────────────────────────
            $table->text('billing_address')->nullable();
            $table->string('billing_email', 200)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('website', 300)->nullable();
            $table->string('logo_path', 500)->nullable();

            // ── Verification (FR-ORG-008) ────────────────────
            $table->enum('verification_status', [
                'unverified', 'pending_review', 'verified', 'rejected',
            ])->default('unverified');
            $table->timestamp('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();

            // ── Settings ─────────────────────────────────────
            $table->string('default_currency', 3)->default('SAR');
            $table->string('timezone', 50)->default('Asia/Riyadh');
            $table->string('locale', 10)->default('ar');

            $table->timestamps();

            $table->index(['account_id']);
            // FK omitted: accounts.id may be bigint on server
        });

        // ═══════════════════════════════════════════════════════════
        // 2. organization_members — FR-ORG-003/005/006/007
        // ═══════════════════════════════════════════════════════════
        if (! Schema::hasTable('organization_members')) {
            Schema::create('organization_members', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
                $table->uuid('user_id');
                $table->uuid('role_id')->nullable();

                $table->enum('membership_role', ['owner', 'admin', 'member'])->default('member');
                $table->enum('status', ['active', 'suspended', 'removed'])->default('active');

                // ── Financial visibility (FR-ORG-005) ────────────
                $table->boolean('can_view_financial')->default(false);

                $table->json('custom_permissions')->nullable();
                $table->timestamp('joined_at')->nullable();
                $table->timestamp('suspended_at')->nullable();
                $table->string('suspended_reason', 300)->nullable();

                $table->timestamps();

                $table->unique(['organization_id', 'user_id']);
                $table->index(['user_id', 'status']);
                // FKs user_id, role_id omitted for server compatibility
            });
        }

        // ═══════════════════════════════════════════════════════════
        // 3. organization_invites — FR-ORG-003
        // ═══════════════════════════════════════════════════════════
        if (! Schema::hasTable('organization_invites')) {
            Schema::create('organization_invites', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
                $table->uuid('invited_by');

                $table->string('email', 200)->nullable();
                $table->string('phone', 20)->nullable();
                $table->string('token', 64)->unique();
                $table->uuid('role_id')->nullable();
                $table->enum('membership_role', ['admin', 'member'])->default('member');

                $table->enum('status', ['pending', 'accepted', 'expired', 'cancelled'])->default('pending');
                $table->timestamp('expires_at');
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->unsignedInteger('resend_count')->default(0);

                $table->timestamps();

                $table->index(['organization_id', 'status']);
                $table->index(['email', 'status']);
                // FKs invited_by, role_id omitted for server compatibility
            });
        }

        // ═══════════════════════════════════════════════════════════
        // 4. organization_wallets — FR-ORG-009/010
        // ═══════════════════════════════════════════════════════════
        if (! Schema::hasTable('organization_wallets')) {
            Schema::create('organization_wallets', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();

                $table->string('currency', 3)->default('SAR');
                $table->decimal('balance', 12, 2)->default(0);
                $table->decimal('reserved_balance', 12, 2)->default(0);   // FR-SH-016 holds
                $table->decimal('low_balance_threshold', 12, 2)->default(100);

                // ── Wallet settings (FR-ORG-010) ─────────────────
                $table->boolean('is_active')->default(true);
                $table->boolean('allow_negative')->default(false);
                $table->boolean('auto_topup_enabled')->default(false);
                $table->decimal('auto_topup_amount', 12, 2)->nullable();
                $table->decimal('auto_topup_threshold', 12, 2)->nullable();

                $table->json('freeze_policy')->nullable();

                $table->timestamps();

                $table->unique(['organization_id']);
            });
        }

        // ═══════════════════════════════════════════════════════════
        // 5. permission_catalog — FR-ORG-004/005/006
        // ═══════════════════════════════════════════════════════════
        if (! Schema::hasTable('permission_catalog')) {
            Schema::create('permission_catalog', function (Blueprint $table) {
                $table->uuid('id')->primary();

                $table->string('key', 100)->unique();             // e.g. shipments.create, finance.view
                $table->string('name', 200);
                $table->text('description')->nullable();
                $table->string('module', 50);                     // IAM, SH, PAY, RPT, etc.
                $table->enum('category', ['operational', 'financial', 'admin'])->default('operational');

                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);

                $table->timestamps();

                $table->index(['module', 'category']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_catalog');
        Schema::dropIfExists('organization_wallets');
        Schema::dropIfExists('organization_invites');
        Schema::dropIfExists('organization_members');
        Schema::dropIfExists('organizations');
    }
};
