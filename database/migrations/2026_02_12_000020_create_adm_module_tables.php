<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADM Module — Platform Administration
 * FR-ADM-001→010 (10 requirements)
 *
 * Tables:
 *   1. system_settings         — FR-ADM-001: Platform-level configs
 *   2. integration_health_logs — FR-ADM-002: Integration health monitoring
 *   3. support_tickets         — FR-ADM-008: Support ticket management
 *   4. support_ticket_replies  — FR-ADM-008: Ticket replies/thread
 *   5. api_keys                — FR-ADM-009: API key management
 *   6. feature_flags           — FR-ADM-010: Feature flags & experiments
 *   7. tax_rules               — FR-ADM-005: Tax rules per region
 *   8. role_templates          — FR-ADM-006: Default role templates
 */
return new class extends Migration
{
    public function up(): void
    {
        // ═══════════════════════════════════════════════════════════
        // 1. system_settings — FR-ADM-001
        // ═══════════════════════════════════════════════════════════
        if (! Schema::hasTable('system_settings')) {
            Schema::create('system_settings', function (Blueprint $table) {
                $table->uuid('id')->primary();

                $table->string('group', 100);             // carrier, platform, billing, notification
                $table->string('key', 200);
                $table->text('value')->nullable();
                $table->string('type', 20)->default('string'); // string, integer, boolean, json, encrypted
                $table->text('description')->nullable();

                $table->boolean('is_sensitive')->default(false);
                $table->string('updated_by', 100)->nullable();

                $table->timestamps();

                $table->unique(['group', 'key']);
            });
        }

        // ═══════════════════════════════════════════════════════════
        // 2. integration_health_logs — FR-ADM-002/006
        // ═══════════════════════════════════════════════════════════
        if (! Schema::hasTable('integration_health_logs')) {
            Schema::create('integration_health_logs', function (Blueprint $table) {
                $table->uuid('id')->primary();

                $table->string('service', 100);               // dhl_api, aramex_api, database, redis, etc.
                $table->enum('status', ['healthy', 'degraded', 'down'])->default('healthy');
                $table->integer('response_time_ms')->nullable();
                $table->float('error_rate')->default(0);
                $table->integer('total_requests')->default(0);
                $table->integer('failed_requests')->default(0);

                $table->text('error_message')->nullable();
                $table->string('correlation_id', 200)->nullable();
                $table->json('metadata')->nullable();

                $table->timestamp('checked_at');
                $table->timestamps();

                $table->index(['service', 'checked_at']);
                $table->index(['status', 'checked_at']);
            });
        }

        // ═══════════════════════════════════════════════════════════
        // 3. support_tickets — FR-ADM-008
        // ═══════════════════════════════════════════════════════════
        if (! Schema::hasTable('support_tickets')) {
            Schema::create('support_tickets', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('account_id');
                $table->uuid('user_id');

                $table->string('ticket_number', 20)->unique();
                $table->string('subject', 300);
                $table->text('description');
                $table->enum('category', [
                    'shipping', 'billing', 'technical', 'account', 'carrier', 'general',
                ])->default('general');

                $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
                $table->enum('status', ['open', 'in_progress', 'waiting_customer', 'waiting_agent', 'resolved', 'closed'])->default('open');

                // ── Context ──────────────────────────────────────
                $table->string('entity_type', 100)->nullable();   // shipment, payment, etc.
                $table->string('entity_id', 100)->nullable();

                // ── Assignment ───────────────────────────────────
                $table->uuid('assigned_to')->nullable();
                $table->string('assigned_team', 100)->nullable();

                $table->timestamp('first_response_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->text('resolution_notes')->nullable();

                $table->timestamps();

                $table->index(['account_id', 'status']);
                $table->index(['assigned_to', 'status']);
                $table->index(['status', 'priority']);
                // FKs omitted: accounts.id, users.id may be bigint on server
            });
        }

        // ═══════════════════════════════════════════════════════════
        // 4. support_ticket_replies — FR-ADM-008
        // ═══════════════════════════════════════════════════════════
        if (! Schema::hasTable('support_ticket_replies')) {
            Schema::create('support_ticket_replies', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('ticket_id');
                $table->uuid('user_id');

                $table->text('body');
                $table->boolean('is_internal_note')->default(false);
                $table->json('attachments')->nullable();

                $table->timestamps();
                $table->index('ticket_id');
                $table->index('user_id');
                // FKs omitted: support_tickets may pre-exist; users.id may be bigint on server
            });
        }

        // ═══════════════════════════════════════════════════════════
        // 5. api_keys — FR-ADM-009
        // ═══════════════════════════════════════════════════════════
        if (! Schema::hasTable('api_keys')) {
            Schema::create('api_keys', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('account_id');
                $table->uuid('created_by');

                $table->string('name', 200);
                $table->string('key_prefix', 10);                   // First 8 chars for display
                $table->string('key_hash', 64)->unique();            // SHA-256 hash
                $table->json('scopes')->nullable();                  // ['shipments:read', 'shipments:write']
                $table->json('allowed_ips')->nullable();

                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->boolean('is_active')->default(true);

                $table->timestamps();

                $table->index(['key_hash', 'is_active']);
                // FKs omitted for server compatibility
            });
        }

        // ═══════════════════════════════════════════════════════════
        // 6. feature_flags — FR-ADM-010
        // ═══════════════════════════════════════════════════════════
        if (! Schema::hasTable('feature_flags')) {
            Schema::create('feature_flags', function (Blueprint $table) {
                $table->uuid('id')->primary();

                $table->string('key', 100)->unique();
                $table->string('name', 200);
                $table->text('description')->nullable();

                $table->boolean('is_enabled')->default(false);
                $table->unsignedTinyInteger('rollout_percentage')->default(0);  // 0-100%
                $table->json('target_accounts')->nullable();
                $table->json('target_plans')->nullable();

                $table->string('created_by', 100)->nullable();
                $table->timestamps();
            });
        }

        // ═══════════════════════════════════════════════════════════
        // 7. tax_rules — FR-ADM-005
        // ═══════════════════════════════════════════════════════════
        if (! Schema::hasTable('tax_rules')) {
            Schema::create('tax_rules', function (Blueprint $table) {
                $table->uuid('id')->primary();

                $table->string('name', 200);
                $table->string('country_code', 2);
                $table->string('region', 100)->nullable();
                $table->decimal('rate', 5, 2);                      // e.g. 15.00%
                $table->enum('applies_to', ['shipping', 'subscription', 'all'])->default('all');

                $table->boolean('is_active')->default(true);
                $table->timestamp('effective_from')->nullable();
                $table->timestamp('effective_to')->nullable();

                $table->timestamps();

                $table->index(['country_code', 'is_active']);
            });
        }

        // ═══════════════════════════════════════════════════════════
        // 8. role_templates — FR-ADM-006/003
        // ═══════════════════════════════════════════════════════════
        if (! Schema::hasTable('role_templates')) {
            Schema::create('role_templates', function (Blueprint $table) {
                $table->uuid('id')->primary();

                $table->string('name', 100);
                $table->string('slug', 50)->unique();
                $table->text('description')->nullable();
                $table->json('permissions');

                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);

                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('role_templates');
        Schema::dropIfExists('tax_rules');
        Schema::dropIfExists('feature_flags');
        Schema::dropIfExists('api_keys');
        Schema::dropIfExists('support_ticket_replies');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('integration_health_logs');
        Schema::dropIfExists('system_settings');
    }
};
