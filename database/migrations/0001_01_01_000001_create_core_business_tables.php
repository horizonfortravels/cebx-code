<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Shipments ──
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference_number')->unique()->index();
            $table->enum('type', ['domestic', 'international', 'return'])->default('domestic');

            // Sender
            $table->string('sender_name');
            $table->string('sender_phone')->nullable();
            $table->string('sender_city');
            $table->string('sender_address')->nullable();

            // Recipient
            $table->string('recipient_name');
            $table->string('recipient_phone');
            $table->string('recipient_city');
            $table->string('recipient_country')->default('SA');
            $table->string('recipient_address')->nullable();
            $table->string('recipient_postal_code')->nullable();

            // Carrier
            $table->string('carrier_code')->nullable();
            $table->string('carrier_name')->nullable();
            $table->string('carrier_tracking_number')->nullable();

            // Package
            $table->decimal('weight', 8, 2)->default(0);
            $table->integer('pieces')->default(1);
            $table->string('content_description')->nullable();
            $table->decimal('declared_value', 10, 2)->default(0);
            $table->boolean('is_cod')->default(false);
            $table->decimal('cod_amount', 10, 2)->default(0);

            // Pricing
            $table->decimal('shipping_cost', 10, 2)->default(0);
            $table->decimal('insurance_cost', 10, 2)->default(0);
            $table->decimal('vat_amount', 10, 2)->default(0);
            $table->decimal('total_cost', 10, 2)->default(0);

            // Status
            $table->string('status')->default('pending')->index();
            $table->string('source')->default('manual')->comment('manual, api, store_sync');
            $table->foreignId('order_id')->nullable();
            $table->string('label_url')->nullable();

            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });

        // ── Shipment Tracking Events ──
        Schema::create('shipment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->string('description');
            $table->string('location')->nullable();
            $table->timestamp('event_at');
            $table->timestamps();
        });

        // ── Stores ──
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('platform', ['salla', 'zid', 'shopify', 'woocommerce'])->index();
            $table->string('store_url')->nullable();
            $table->string('api_key')->nullable();
            $table->string('api_secret')->nullable();
            $table->enum('status', ['connected', 'disconnected', 'error'])->default('connected');
            $table->integer('orders_count')->default(0);
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
        });

        // ── Orders ──
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $table->string('order_number')->index();
            $table->string('platform_order_id')->nullable();
            $table->string('customer_name');
            $table->string('customer_phone')->nullable();
            $table->string('customer_city')->nullable();
            $table->string('customer_address')->nullable();
            $table->integer('items_count')->default(1);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->string('status')->default('new')->index();
            $table->foreignId('shipment_id')->nullable();
            $table->timestamps();
        });

        // ── Wallets ──
        Schema::create('wallets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->decimal('available_balance', 12, 2)->default(0);
            $table->decimal('pending_balance', 12, 2)->default(0);
            $table->timestamps();
        });

        // ── Wallet Transactions ──
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('reference_number')->nullable()->index();
            $table->enum('type', ['credit', 'debit', 'refund', 'payout'])->index();
            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_after', 12, 2)->default(0);
            $table->string('status')->default('completed');
            $table->string('payment_method')->nullable();
            $table->timestamps();
        });

        // ── Addresses ──
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable()->comment('المنزل, المكتب');
            $table->string('name');
            $table->string('phone');
            $table->string('city');
            $table->string('district')->nullable();
            $table->string('street')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->default('SA');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        // ── Support Tickets ──
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reference_number')->nullable()->unique();
            $table->string('subject');
            $table->text('body');
            $table->string('category')->default('general');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->string('status')->default('open');
            $table->foreignId('assigned_to')->nullable()->comment('agent user id');
            $table->foreignId('shipment_id')->nullable();
            $table->timestamps();
        });

        Schema::create('ticket_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->boolean('is_agent')->default(false);
            $table->timestamps();
        });

        // ── Notifications ──
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->default('system')->index();
            $table->string('title');
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        // ── Invitations ──
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('name')->nullable();
            $table->string('role_name')->default('مشغّل');
            $table->string('job_title')->nullable();
            $table->string('token')->unique();
            $table->enum('status', ['pending', 'accepted', 'expired', 'cancelled'])->default('pending');
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('ticket_replies');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallets');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('stores');
        Schema::dropIfExists('shipment_events');
        Schema::dropIfExists('shipments');
    }
};
