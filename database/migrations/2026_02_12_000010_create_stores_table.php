<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FR-IAM-009: Multi-Store Support
 *
 * Stores represent sales channels / sub-entities within an account.
 * Each account can have multiple stores (up to a configurable limit).
 * Stores serve as the foundation for the ST (Sales Channel) module.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stores')) {
            return;
        }

        Schema::create('stores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('account_id');
            $table->string('name', 150);
            $table->string('slug', 150);
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->enum('platform', [
                'manual',        // Direct / manual store
                'shopify',
                'woocommerce',
                'salla',
                'zid',
                'custom_api',
            ])->default('manual');

            // Store Contact & Address
            $table->string('contact_name', 150)->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->string('contact_email', 255)->nullable();
            $table->string('address_line_1', 255)->nullable();
            $table->string('address_line_2', 255)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state_province', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 3)->default('SA');

            // Store Settings
            $table->string('currency', 3)->default('SAR');
            $table->string('language', 10)->default('ar');
            $table->string('timezone', 50)->default('Asia/Riyadh');
            $table->string('logo_path', 500)->nullable();
            $table->string('website_url', 500)->nullable();

            // Store Connection (for sales channel integration — FR-ST module)
            $table->string('external_store_id', 255)->nullable()->comment('ID in external platform');
            $table->string('external_store_url', 500)->nullable()->comment('Store URL on platform');
            $table->json('connection_config')->nullable()->comment('OAuth tokens, API keys (encrypted)');
            $table->enum('connection_status', ['disconnected', 'connected', 'error'])->default('disconnected');
            $table->timestamp('last_synced_at')->nullable();

            // Metadata
            $table->boolean('is_default')->default(false)->comment('Default store for the account');
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->unique(['account_id', 'slug']);
            $table->unique(['account_id', 'name']);
            $table->index(['account_id', 'status']);
            $table->index(['account_id', 'platform']);
            $table->index('created_by');
            // FKs omitted: accounts.id and users.id may be bigint on server
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
