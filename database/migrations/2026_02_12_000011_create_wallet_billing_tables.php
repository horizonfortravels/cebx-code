<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FR-IAM-017 + FR-IAM-019 + FR-IAM-020:
 * Wallet, Ledger, Payment Methods with RBAC and disabled-account masking.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wallets')) {
            return;
        }

        // ── Wallet: one per account ──────────────────────────────
        Schema::create('wallets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('account_id')->unique();
            $table->string('currency', 3)->default('SAR');
            $table->decimal('available_balance', 15, 2)->default(0);
            $table->decimal('locked_balance', 15, 2)->default(0)->comment('Reserved for pending shipments');
            $table->decimal('low_balance_threshold', 15, 2)->nullable()->comment('Alert when balance drops below');
            $table->enum('status', ['active', 'frozen', 'closed'])->default('active');
            $table->timestamps();

            $table->index('account_id');
            // FK omitted: accounts.id may be bigint on server
        });

        // ── Ledger: append-only transaction log ──────────────────
        if (! Schema::hasTable('wallet_ledger_entries')) {
            Schema::create('wallet_ledger_entries', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('wallet_id');
                $table->enum('type', ['topup', 'debit', 'refund', 'adjustment', 'lock', 'unlock']);
                $table->decimal('amount', 15, 2)->comment('Positive for credit, negative for debit');
                $table->decimal('running_balance', 15, 2);
                $table->string('reference_type', 50)->nullable()->comment('shipment, topup, refund, etc.');
                $table->string('reference_id', 100)->nullable();
                $table->uuid('actor_user_id')->nullable();
                $table->string('description', 500)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at');

                $table->index(['wallet_id', 'created_at']);
                $table->index(['wallet_id', 'type']);
                $table->index(['reference_type', 'reference_id']);
                $table->index('actor_user_id');
                // FKs omitted: wallets.id / users.id may differ by migration source
            });
        }

        // ── Payment Methods: stored cards/methods ────────────────
        if (! Schema::hasTable('payment_methods')) {
            Schema::create('payment_methods', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('account_id');
                $table->enum('type', ['card', 'bank_transfer', 'wallet_gateway'])->default('card');
                $table->string('label', 100)->nullable()->comment('User-friendly name');
                $table->string('provider', 50)->nullable()->comment('visa, mastercard, mada, etc.');
                $table->string('last_four', 4)->nullable();
                $table->string('expiry_month', 2)->nullable();
                $table->string('expiry_year', 4)->nullable();
                $table->string('cardholder_name', 150)->nullable();
                $table->text('gateway_token')->nullable()->comment('Encrypted token from payment gateway');
                $table->string('gateway_customer_id', 255)->nullable();
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->boolean('is_masked_override')->default(false)->comment('FR-IAM-020: force mask when account disabled');
                $table->uuid('added_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['account_id', 'is_active']);
                $table->index('added_by');
                // FKs omitted: accounts.id and users.id may be bigint on server
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('wallet_ledger_entries');
        Schema::dropIfExists('wallets');
    }
};
