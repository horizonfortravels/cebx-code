<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TR Module — Tracking & Status Normalization
 * FR-TR-001→007 (7 requirements)
 *
 * Tables:
 *   1. tracking_events        — FR-TR-001/002/003: Raw & normalized tracking events
 *   2. tracking_webhooks      — FR-TR-001/002: Webhook receipt log
 *   3. status_mappings        — FR-TR-004/006: Carrier→Unified status mapping
 *   4. tracking_subscriptions — FR-TR-004: Status change subscribers
 *   5. shipment_exceptions    — FR-TR-007: Exception management
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tracking_events')) {
            return;
        }

        // ═══════════════════════════════════════════════════════════════
        // 1. tracking_events — FR-TR-001/003/005
        //    Every tracking event (raw from carrier + normalized)
        // ═══════════════════════════════════════════════════════════════
        Schema::create('tracking_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('shipment_id');
            $table->uuid('account_id');

            // ── Carrier Raw Data ─────────────────────────────
            $table->string('carrier_code', 50)->default('dhl');
            $table->string('tracking_number', 100)->index();
            $table->string('raw_status', 200);                // DHL original status
            $table->text('raw_description')->nullable();       // DHL description
            $table->string('raw_status_code', 50)->nullable(); // DHL status code

            // ── Normalized / Unified Status (FR-TR-004) ──────
            $table->string('unified_status', 50)->index();     // Our internal status
            $table->text('unified_description')->nullable();

            // ── Event Details (FR-TR-005) ────────────────────
            $table->timestamp('event_time');                    // When it happened at carrier
            $table->string('location_city', 200)->nullable();
            $table->string('location_country', 5)->nullable();
            $table->string('location_code', 50)->nullable();   // Facility code
            $table->text('location_description')->nullable();
            $table->string('signatory', 200)->nullable();      // For delivered events

            // ── Source & Dedup (FR-TR-001/003) ───────────────
            $table->enum('source', ['webhook', 'polling', 'manual', 'api'])->default('webhook');
            $table->string('dedup_key', 128)->unique();        // Prevent duplicate events
            $table->integer('sequence_number')->nullable();     // For ordering
            $table->string('webhook_id', 100)->nullable();     // Reference to tracking_webhooks

            // ── Processing ───────────────────────────────────
            $table->boolean('is_processed')->default(true);
            $table->boolean('notified_store')->default(false);     // FR-TR-006
            $table->boolean('notified_subscribers')->default(false); // FR-TR-004
            $table->boolean('is_exception')->default(false);        // FR-TR-007

            // ── Raw Payload ──────────────────────────────────
            $table->json('raw_payload')->nullable();

            $table->timestamps();

            $table->index(['shipment_id', 'event_time']);
            $table->index(['shipment_id', 'unified_status']);
            $table->index(['account_id', 'unified_status']);
            $table->index(['carrier_code', 'tracking_number']);
            // FKs omitted: shipments.id, accounts.id may be bigint on server
        });

        // ═══════════════════════════════════════════════════════════════
        // 2. tracking_webhooks — FR-TR-001/002
        //    Log of received webhooks (security + audit)
        // ═══════════════════════════════════════════════════════════════
        if (! Schema::hasTable('tracking_webhooks')) {
        Schema::create('tracking_webhooks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('carrier_code', 50)->default('dhl');

            // ── Verification (FR-TR-002) ─────────────────────
            $table->string('signature', 500)->nullable();
            $table->boolean('signature_valid')->nullable();
            $table->string('message_reference', 200)->nullable();
            $table->string('replay_token', 200)->nullable()->index(); // Prevent replay

            // ── HTTP Details ─────────────────────────────────
            $table->string('source_ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->json('headers')->nullable();

            // ── Content ──────────────────────────────────────
            $table->string('event_type', 100)->nullable();
            $table->string('tracking_number', 100)->nullable()->index();
            $table->json('payload')->nullable();
            $table->integer('payload_size')->nullable();

            // ── Processing ───────────────────────────────────
            $table->enum('status', ['received', 'validated', 'processed', 'rejected', 'failed'])->default('received');
            $table->text('rejection_reason')->nullable();
            $table->integer('events_extracted')->default(0);
            $table->integer('processing_time_ms')->nullable();

            $table->timestamps();

            $table->index(['carrier_code', 'status']);
            $table->index(['tracking_number', 'created_at']);
        });
        }

        // ═══════════════════════════════════════════════════════════════
        // 3. status_mappings — FR-TR-004/006
        //    Carrier → Unified status mapping (configurable)
        // ═══════════════════════════════════════════════════════════════
        if (! Schema::hasTable('status_mappings')) {
        Schema::create('status_mappings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('carrier_code', 50);
            $table->string('carrier_status', 200);        // Raw carrier status
            $table->string('carrier_status_code', 50)->nullable();
            $table->string('unified_status', 50);          // Our status
            $table->string('unified_description', 500)->nullable();

            // ── Store Sync (FR-TR-006) ───────────────────────
            $table->boolean('notify_store')->default(false);
            $table->string('store_status', 100)->nullable(); // Status to send to store

            // ── Classification ───────────────────────────────
            $table->boolean('is_terminal')->default(false);    // Delivered/Returned/Lost
            $table->boolean('is_exception')->default(false);
            $table->boolean('requires_action')->default(false); // FR-TR-007

            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['carrier_code', 'carrier_status', 'carrier_status_code'], 'status_mapping_unique');
            $table->index(['carrier_code', 'unified_status']);
        });
        }

        // ═══════════════════════════════════════════════════════════════
        // 4. tracking_subscriptions — FR-TR-004
        //    Users/systems subscribed to shipment status changes
        // ═══════════════════════════════════════════════════════════════
        if (! Schema::hasTable('tracking_subscriptions')) {
        Schema::create('tracking_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('shipment_id');
            $table->uuid('account_id');

            // ── Subscriber ───────────────────────────────────
            $table->enum('channel', ['email', 'sms', 'webhook', 'in_app'])->default('email');
            $table->string('destination', 500); // email addr, phone, webhook URL
            $table->string('subscriber_name', 200)->nullable();

            // ── Preferences ──────────────────────────────────
            $table->json('event_types')->nullable(); // null = all events
            $table->string('language', 5)->default('ar');
            $table->boolean('is_active')->default(true);

            // ── Stats ────────────────────────────────────────
            $table->integer('notifications_sent')->default(0);
            $table->timestamp('last_notified_at')->nullable();

            $table->timestamps();

            $table->index(['shipment_id', 'channel']);
            $table->index(['account_id', 'is_active']);
            // FKs omitted for server compatibility
        });
        }

        // ═══════════════════════════════════════════════════════════════
        // 5. shipment_exceptions — FR-TR-007
        //    Exception management with reasons & suggested actions
        // ═══════════════════════════════════════════════════════════════
        if (! Schema::hasTable('shipment_exceptions')) {
        Schema::create('shipment_exceptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('shipment_id');
            $table->uuid('tracking_event_id')->nullable();
            $table->uuid('account_id');

            // ── Exception Details ────────────────────────────
            $table->string('exception_code', 100)->index();
            $table->text('reason');
            $table->text('carrier_reason')->nullable();
            $table->text('suggested_action')->nullable();

            // ── Status ───────────────────────────────────────
            $table->enum('status', [
                'open',
                'acknowledged',
                'in_progress',
                'resolved',
                'escalated',
                'closed',
            ])->default('open');

            // ── Resolution ───────────────────────────────────
            $table->text('resolution_notes')->nullable();
            $table->string('resolved_by', 100)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('escalated_at')->nullable();

            // ── Priority ─────────────────────────────────────
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->boolean('requires_customer_action')->default(false);

            $table->timestamps();

            $table->index(['shipment_id', 'status']);
            $table->index(['account_id', 'status', 'priority']);
            $table->index('tracking_event_id');
            // FKs omitted for server compatibility
        });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_exceptions');
        Schema::dropIfExists('tracking_subscriptions');
        Schema::dropIfExists('status_mappings');
        Schema::dropIfExists('tracking_webhooks');
        Schema::dropIfExists('tracking_events');
    }
};
