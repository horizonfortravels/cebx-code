<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جدول wallet_transactions مطلوب من الكود (WalletWebController، PageController، ShipmentWebController)
 * ولم يكن منشأً في migrations الـ 2026 (التي استخدمت wallet_ledger_entries فقط).
 * الهيكل متوافق مع نموذج WalletTransaction وربط account_id بـ accounts (uuid).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wallet_transactions')) {
            return;
        }

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->foreignUuid('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('reference_number')->nullable()->index();
            $table->string('type', 20)->index(); // credit, debit, refund, payout
            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_after', 12, 2)->default(0);
            $table->string('status', 30)->default('completed');
            $table->string('payment_method')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
