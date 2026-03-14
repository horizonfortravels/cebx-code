<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PAY Module — Payments & Subscription
 * FR-PAY-001→011 (11 requirements)
 *
 * Tables:
 *   1. subscription_plans     — FR-PAY-003/005: Plans with pricing tiers
 *   2. subscriptions          — FR-PAY-003/005: Account subscription lifecycle
 *   3. payment_transactions   — FR-PAY-001/002/004/008: All payment records
 *   4. invoices               — FR-PAY-005: Invoice/receipt generation
 *   5. invoice_items          — FR-PAY-005: Line items
 *   6. payment_gateways       — FR-PAY-004: Gateway configurations
 *   7. promo_codes            — FR-PAY-007: Promotional codes/discounts
 *   8. promo_code_usages      — FR-PAY-007: Usage tracking
 *   9. balance_alerts         — FR-PAY-011: Low balance alert thresholds
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('subscription_plans')) {
            return;
        }

        // ═══════════════════════════════════════════════════════════
        // 1. subscription_plans — FR-PAY-003/005
        // ═══════════════════════════════════════════════════════════
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('name', 200);
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();

            // ── Pricing ──────────────────────────────────────
            $table->decimal('monthly_price', 12, 2);
            $table->decimal('yearly_price', 12, 2);
            $table->string('currency', 3)->default('SAR');

            // ── Limits & Features ────────────────────────────
            $table->integer('max_shipments_per_month')->nullable();
            $table->integer('max_stores')->nullable();
            $table->integer('max_users')->nullable();
            $table->decimal('shipping_discount_pct', 5, 2)->default(0);
            $table->json('features')->nullable();

            // ── Pricing Markup (FR-PAY-006 via RT) ───────────
            $table->decimal('markup_multiplier', 5, 4)->default(1.0000);

            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);

            $table->timestamps();
        });

        // ═══════════════════════════════════════════════════════════
        // 2. subscriptions — FR-PAY-003/005
        // ═══════════════════════════════════════════════════════════
        if (! Schema::hasTable('subscriptions')) {
            Schema::create('subscriptions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('account_id');
                $table->foreignUuid('plan_id')->constrained('subscription_plans');

                $table->enum('billing_cycle', ['monthly', 'yearly']);
                $table->enum('status', ['active', 'expired', 'cancelled', 'suspended', 'trial'])->default('trial');

                $table->timestamp('starts_at')->nullable();
                $table->timestamp('expires_at')->nullable();

                $table->timestamp('cancelled_at')->nullable();
                $table->timestamp('renewed_at')->nullable();

                // ── Auto-renew ───────────────────────────────────
                $table->boolean('auto_renew')->default(true);
                $table->string('payment_method_id', 200)->nullable();

                // ── Trial ────────────────────────────────────────
                $table->integer('trial_days')->default(0);
                $table->boolean('trial_used')->default(false);

                $table->decimal('amount_paid', 12, 2)->default(0);
                $table->string('currency', 3)->default('SAR');

                $table->timestamps();

                $table->index(['account_id', 'status']);
                $table->index(['expires_at', 'status']);
                // FK account_id omitted: accounts.id may be bigint on server
            });
        }

        // ═══════════════════════════════════════════════════════════
        // 3. payment_transactions — FR-PAY-001/002/004/008
        //    Idempotent payment records (FR-PAY-002)
        // ═══════════════════════════════════════════════════════════
        if (! Schema::hasTable('payment_transactions')) {
            Schema::create('payment_transactions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('account_id');
                $table->uuid('user_id')->nullable();

                // ── Idempotency (FR-PAY-002) ─────────────────────
                $table->string('idempotency_key', 200)->unique();

                // ── Type & Context ───────────────────────────────
                $table->enum('type', [
                    'wallet_topup', 'shipping_charge', 'subscription',
                    'refund', 'adjustment', 'promo_credit',
                ]);
                $table->string('entity_type', 100)->nullable();  // shipment, subscription, etc.
                $table->string('entity_id', 100)->nullable();

                // ── Amounts ──────────────────────────────────────
                $table->decimal('amount', 12, 2);
                $table->decimal('tax_amount', 12, 2)->default(0);       // FR-PAY-006
                $table->decimal('discount_amount', 12, 2)->default(0);  // FR-PAY-007
                $table->decimal('net_amount', 12, 2);
                $table->string('currency', 3)->default('SAR');

                // ── Direction ────────────────────────────────────
                $table->enum('direction', ['credit', 'debit']);

                // ── Balance (wallet context) ─────────────────────
                $table->decimal('balance_before', 12, 2)->nullable();
                $table->decimal('balance_after', 12, 2)->nullable();

                // ── Status ───────────────────────────────────────
                $table->enum('status', [
                    'pending', 'processing', 'captured', 'completed',
                    'failed', 'refunded', 'partially_refunded', 'cancelled',
                ])->default('pending');
                $table->text('failure_reason')->nullable();

                // ── Gateway (FR-PAY-004) ─────────────────────────
                $table->string('gateway', 100)->nullable();          // stripe, paypal, wallet
                $table->string('gateway_transaction_id', 200)->nullable();
                $table->json('gateway_response')->nullable();
                $table->string('payment_method', 100)->nullable();   // card, bank, wallet

                // ── Promo (FR-PAY-007) ───────────────────────────
                $table->string('promo_code_id', 100)->nullable();

                // ── Refund Reference ─────────────────────────────
                $table->foreignUuid('refund_of_id')->nullable()->constrained('payment_transactions')->nullOnDelete();

                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['account_id', 'type', 'created_at']);
                $table->index(['status', 'created_at']);
                $table->index(['entity_type', 'entity_id']);
                                $table->index(['gateway', 'gateway_transaction_id']);
                // FKs account_id, user_id omitted for server compatibility
            });
        }

        // ═══════════════════════════════════════════════════════════
        // 4. invoices — FR-PAY-005
        // ═══════════════════════════════════════════════════════════
        if (! Schema::hasTable('invoices')) {
            Schema::create('invoices', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('account_id');
                $table->foreignUuid('transaction_id')->nullable()->constrained('payment_transactions')->nullOnDelete();

                $table->string('invoice_number', 50)->unique();
                $table->enum('type', ['invoice', 'receipt', 'credit_note']);

                $table->decimal('subtotal', 12, 2);
                $table->decimal('tax_amount', 12, 2)->default(0);
                $table->decimal('discount_amount', 12, 2)->default(0);
                $table->decimal('total', 12, 2);
                $table->string('currency', 3)->default('SAR');

                $table->decimal('tax_rate', 5, 2)->default(15.00);  // Saudi VAT

                // ── Billing Info ─────────────────────────────────
                $table->string('billing_name', 200)->nullable();
                $table->text('billing_address')->nullable();
                $table->string('tax_number', 50)->nullable();        // VAT registration

                $table->enum('status', ['draft', 'issued', 'paid', 'void'])->default('draft');
                $table->timestamp('issued_at')->nullable();
                $table->timestamp('due_at')->nullable();
                $table->timestamp('paid_at')->nullable();

                $table->string('pdf_path', 500)->nullable();

                $table->timestamps();

                $table->index(['account_id', 'created_at']);
                // FK account_id omitted for server compatibility
            });
        }

        // ═══════════════════════════════════════════════════════════
        // 5. invoice_items — FR-PAY-005
        // ═══════════════════════════════════════════════════════════
        if (! Schema::hasTable('invoice_items')) {
            Schema::create('invoice_items', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('invoice_id')->constrained('invoices')->cascadeOnDelete();

                $table->string('description', 500);
                $table->integer('quantity')->default(1);
                $table->decimal('unit_price', 12, 2);
                $table->decimal('tax_amount', 12, 2)->default(0);
                $table->decimal('total', 12, 2);

                $table->string('entity_type', 100)->nullable();
                $table->string('entity_id', 100)->nullable();

                $table->timestamps();
            });
        }

        // ═══════════════════════════════════════════════════════════
        // 6. payment_gateways — FR-PAY-004
        // ═══════════════════════════════════════════════════════════
        if (! Schema::hasTable('payment_gateways')) {
            Schema::create('payment_gateways', function (Blueprint $table) {
                $table->uuid('id')->primary();

                $table->string('name', 100);
                $table->string('slug', 50)->unique();            // stripe, paypal, mada, stc_pay
                $table->string('provider', 100);

                $table->json('config')->nullable();              // Encrypted API keys
                $table->json('supported_currencies')->nullable();
                $table->json('supported_methods')->nullable();   // card, bank, wallet

                $table->boolean('is_active')->default(true);
                $table->boolean('is_sandbox')->default(false);
                $table->integer('sort_order')->default(0);

                $table->decimal('transaction_fee_pct', 5, 2)->default(0);
                $table->decimal('transaction_fee_fixed', 8, 2)->default(0);

                $table->timestamps();
            });
        }

        // ═══════════════════════════════════════════════════════════
        // 7. promo_codes — FR-PAY-007
        // ═══════════════════════════════════════════════════════════
        if (! Schema::hasTable('promo_codes')) {
            Schema::create('promo_codes', function (Blueprint $table) {
                $table->uuid('id')->primary();

                $table->string('code', 50)->unique();
                $table->text('description')->nullable();

                $table->enum('discount_type', ['percentage', 'fixed']);
                $table->decimal('discount_value', 12, 2);
                $table->decimal('min_order_amount', 12, 2)->nullable();
                $table->decimal('max_discount_amount', 12, 2)->nullable();
                $table->string('currency', 3)->default('SAR');

                // ── Applicability ────────────────────────────────
                $table->enum('applies_to', ['shipping', 'subscription', 'both'])->default('both');
                $table->json('applicable_plans')->nullable();
                $table->json('applicable_accounts')->nullable();

                // ── Limits ───────────────────────────────────────
                $table->integer('max_total_uses')->nullable();
                $table->integer('max_uses_per_account')->default(1);
                $table->integer('total_used')->default(0);

                $table->timestamp('starts_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->boolean('is_active')->default(true);

                $table->timestamps();

                $table->index(['code', 'is_active']);
            });
        }

        // ═══════════════════════════════════════════════════════════
        // 8. promo_code_usages — FR-PAY-007
        // ═══════════════════════════════════════════════════════════
        if (! Schema::hasTable('promo_code_usages')) {
            Schema::create('promo_code_usages', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('promo_code_id')->constrained('promo_codes')->cascadeOnDelete();
                $table->uuid('account_id');
                $table->foreignUuid('transaction_id')->nullable()->constrained('payment_transactions')->nullOnDelete();

                $table->decimal('discount_applied', 12, 2);
                $table->timestamps();

                $table->index(['promo_code_id', 'account_id']);
                // FK account_id omitted for server compatibility
            });
        }

        // ═══════════════════════════════════════════════════════════
        // 9. balance_alerts — FR-PAY-011
        // ═══════════════════════════════════════════════════════════
        if (! Schema::hasTable('balance_alerts')) {
            Schema::create('balance_alerts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('account_id');
                $table->uuid('user_id')->nullable();

                $table->decimal('threshold_amount', 12, 2);
                $table->string('currency', 3)->default('SAR');
                $table->json('channels')->nullable();              // ['email', 'sms', 'in_app']
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_triggered_at')->nullable();

                $table->timestamps();
                // FKs account_id, user_id omitted for server compatibility
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('balance_alerts');
        Schema::dropIfExists('promo_code_usages');
        Schema::dropIfExists('promo_codes');
        Schema::dropIfExists('payment_gateways');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('subscription_plans');
    }
};
