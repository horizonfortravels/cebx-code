<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BW Module — Billing & Wallet
 * FR-BW-001→010 (10 requirements)
 *
 * Tables:
 *   1. billing_wallets        — FR-BW-001: Per-account wallet with available/reserved balance
 *   2. wallet_topups          — FR-BW-002/003: Top-up lifecycle (Pending/Success/Failed)
 *   3. wallet_ledger_entries  — FR-BW-004/005: Immutable append-only ledger with running balance
 *   4. wallet_holds           — FR-BW-007: Reservation/Hold before label issuance
 *   5. wallet_refunds         — FR-BW-006: Refund records linked to shipments
 *   6. reconciliation_reports — FR-BW-010: Reconciliation between gateway & ledger
 */
return new class extends Migration
{
    public function up(): void
    {
        // ═══════════════════════════════════════════════════════════
        // 1. billing_wallets — FR-BW-001 (skip if from 011)
        // ═══════════════════════════════════════════════════════════
        if (!Schema::hasTable('billing_wallets')) {
        Schema::create('billing_wallets', function (Blueprint $table) {
            $table->uuid('id')->primary();
                        $table->uuid('account_id');
            $table->uuid('organization_id')->nullable();

            $table->string('currency', 3)->default('SAR');
            $table->decimal('available_balance', 14, 2)->default(0);
            $table->decimal('reserved_balance', 14, 2)->default(0);   // FR-BW-007: Holds
            $table->decimal('total_credited', 14, 2)->default(0);     // Lifetime credits
            $table->decimal('total_debited', 14, 2)->default(0);      // Lifetime debits

            // FR-BW-008: Liquidity threshold
            $table->decimal('low_balance_threshold', 14, 2)->nullable();
            $table->boolean('low_balance_notified')->default(false);
            $table->timestamp('low_balance_notified_at')->nullable();

            // Auto top-up settings
            $table->boolean('auto_topup_enabled')->default(false);
            $table->decimal('auto_topup_amount', 14, 2)->nullable();
            $table->decimal('auto_topup_trigger', 14, 2)->nullable(); // Trigger when below

            $table->enum('status', ['active', 'frozen', 'closed'])->default('active');
            $table->boolean('allow_negative')->default(false);

            $table->timestamps();

            $table->unique(['account_id', 'currency']);
            $table->index(['organization_id']);
        });
        }

        if (!Schema::hasTable('wallet_topups')) {
        Schema::create('wallet_topups', function (Blueprint $table) {
            $table->uuid('id')->primary();
                        $table->foreignUuid('wallet_id')->constrained('billing_wallets')->cascadeOnDelete();
            $table->uuid('account_id');

            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('SAR');
            $table->enum('status', ['pending', 'success', 'failed', 'expired'])->default('pending');

            // Payment gateway
            $table->string('payment_gateway', 50)->nullable();        // stripe, moyasar, etc.
            $table->string('payment_reference', 200)->nullable();     // Gateway transaction ID
            $table->string('checkout_url', 1000)->nullable();
            $table->string('payment_method', 50)->nullable();         // card, bank, apple_pay

            // Idempotency (FR-BW-002)
            $table->string('idempotency_key', 200)->nullable()->unique();

            $table->string('initiated_by', 100)->nullable();          // User who started
            $table->text('failure_reason')->nullable();
            $table->json('gateway_metadata')->nullable();

            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            $table->index(['wallet_id', 'status']);
            $table->index(['account_id', 'created_at']);
        });
        }

        if (!Schema::hasTable('wallet_ledger_entries')) {
        Schema::create('wallet_ledger_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('wallet_id')->constrained('billing_wallets')->cascadeOnDelete();

            $table->unsignedBigInteger('sequence');                    // Auto-incrementing per wallet
            $table->string('correlation_id', 200);

            $table->enum('transaction_type', [
                'topup',        // Credit: Top-up success
                'debit',        // Debit: Shipment charge
                'refund',       // Credit: Shipment refund
                'hold',         // Info: Reservation created
                'hold_release', // Info: Reservation released
                'hold_capture', // Debit: Reservation converted to charge
                'adjustment',   // Manual adjustment (credit/debit)
                'reversal',     // Correction entry
            ]);

            $table->enum('direction', ['credit', 'debit']);
            $table->decimal('amount', 14, 2);                         // Always positive
            $table->decimal('running_balance', 14, 2);                // FR-BW-005

            // Reference linking
            $table->string('reference_type', 50)->nullable();         // topup, shipment, refund, hold
            $table->string('reference_id', 100)->nullable();
            $table->string('reversal_of', 100)->nullable();           // ID of entry being reversed

            $table->string('created_by', 100)->nullable();            // user_id or 'system'
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamp('created_at');
            // NO updated_at — IMMUTABLE

            $table->unique(['wallet_id', 'sequence']);
            $table->index(['wallet_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['correlation_id']);
        });
        }

        if (!Schema::hasTable('wallet_holds')) {
        Schema::create('wallet_holds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('wallet_id')->constrained('billing_wallets')->cascadeOnDelete();

            $table->decimal('amount', 14, 2);
            $table->string('shipment_id', 100);
            $table->enum('status', ['active', 'captured', 'released', 'expired'])->default('active');

            $table->string('idempotency_key', 200)->nullable()->unique();

            $table->timestamp('captured_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            $table->unique(['wallet_id', 'shipment_id', 'status']);
            $table->index(['status']);
        });
        }

        if (!Schema::hasTable('wallet_refunds')) {
        Schema::create('wallet_refunds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('wallet_id')->constrained('billing_wallets')->cascadeOnDelete();

            $table->decimal('amount', 14, 2);
            $table->string('shipment_id', 100);
            $table->string('reason', 500);
            $table->enum('initiated_by_type', ['system', 'support', 'user'])->default('system');
            $table->string('initiated_by_id', 100)->nullable();

            $table->string('original_debit_id', 100)->nullable();     // Link to original debit ledger entry
            $table->string('idempotency_key', 200)->nullable()->unique();

            $table->enum('status', ['processed', 'failed'])->default('processed');

            $table->timestamps();

            $table->index(['wallet_id', 'shipment_id']);
        });
        }

        if (!Schema::hasTable('reconciliation_reports')) {
        Schema::create('reconciliation_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->date('report_date');
            $table->string('payment_gateway', 50);

            $table->integer('total_topups')->default(0);
            $table->integer('matched')->default(0);
            $table->integer('unmatched_gateway')->default(0);         // Success in gateway, no ledger
            $table->integer('unmatched_ledger')->default(0);          // Ledger entry, no gateway record
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->decimal('discrepancy_amount', 14, 2)->default(0);

            $table->json('anomalies')->nullable();
            $table->enum('status', ['pending', 'completed', 'reviewed'])->default('pending');
            $table->string('reviewed_by', 100)->nullable();

            $table->timestamps();

            $table->index(['report_date', 'payment_gateway']);
        });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_reports');
        Schema::dropIfExists('wallet_refunds');
        Schema::dropIfExists('wallet_holds');
        Schema::dropIfExists('wallet_ledger_entries');
        Schema::dropIfExists('wallet_topups');
        Schema::dropIfExists('billing_wallets');
    }
};
