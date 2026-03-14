<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NTF Module — Notifications
 * FR-NTF-001→009 (9 requirements)
 *
 * Tables:
 *   1. notification_templates   — FR-NTF-002/004/006: Customizable templates (AR/EN)
 *   2. notification_preferences — FR-NTF-003/004: Per-user/role preferences
 *   3. notifications            — FR-NTF-001/008: Sent notification log
 *   4. notification_channels    — FR-NTF-002: Channel configs (email/SMS/Slack/webhook)
 *   5. notification_schedules   — FR-NTF-007: Scheduled/digest notifications
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notification_templates')) {
            return;
        }

        // ═══════════════════════════════════════════════════════════
        // 1. notification_templates — FR-NTF-002/004/006
        // ═══════════════════════════════════════════════════════════
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('account_id')->nullable();
            // null account_id = system default template

            $table->string('event_type', 100)->index();  // e.g. shipment.delivered, order.created
            $table->string('channel', 50);                // email, sms, in_app, webhook, slack
            $table->string('language', 5)->default('ar'); // FR-NTF-006

            // ── Content ──────────────────────────────────────
            $table->string('subject', 500)->nullable();     // For email
            $table->text('body');                            // Template body with {{variables}}
            $table->text('body_html')->nullable();           // HTML version for email
            $table->string('sender_name', 200)->nullable();
            $table->string('sender_email', 200)->nullable();

            // ── Metadata ─────────────────────────────────────
            $table->json('variables')->nullable();           // Available template variables
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);    // Cannot be deleted by user
            $table->integer('version')->default(1);

            $table->timestamps();

            $table->unique(['account_id', 'event_type', 'channel', 'language'], 'ntf_template_unique');
            $table->index('account_id');
            // FK omitted: accounts.id may be bigint on server
        });

        // ═══════════════════════════════════════════════════════════
        // 2. notification_preferences — FR-NTF-003/004
        // ═══════════════════════════════════════════════════════════
        if (! Schema::hasTable('notification_preferences')) {
            Schema::create('notification_preferences', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->uuid('account_id');

                $table->string('event_type', 100);
                $table->string('channel', 50);
                $table->boolean('enabled')->default(true);

                // ── Override settings ────────────────────────────
                $table->string('language', 5)->nullable();       // Override account language
                $table->string('destination', 500)->nullable();  // Override user's default email/phone

                $table->timestamps();

                $table->unique(['user_id', 'event_type', 'channel'], 'ntf_pref_unique');
                $table->index(['account_id', 'event_type']);
                // FKs omitted: users.id, accounts.id may be bigint on server
            });
        }

        // ═══════════════════════════════════════════════════════════
        // 3. notifications — FR-NTF-001/003/005/008
        //    Complete notification log with delivery tracking
        // ═══════════════════════════════════════════════════════════
        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('account_id');
                $table->uuid('user_id')->nullable();

                // ── Event ────────────────────────────────────────
                $table->string('event_type', 100)->index();
                $table->string('entity_type', 100)->nullable();  // shipment, order, etc.
                $table->string('entity_id', 100)->nullable();
                $table->json('event_data')->nullable();

                // ── Delivery ─────────────────────────────────────
                $table->string('channel', 50);                   // email, sms, in_app, webhook, slack
                $table->string('destination', 500);              // email addr, phone, webhook URL
                $table->string('language', 5)->default('ar');

                // ── Content ──────────────────────────────────────
                $table->string('subject', 500)->nullable();
                $table->text('body')->nullable();
                $table->string('template_id', 100)->nullable();

                // ── Status & Retry (FR-NTF-003) ──────────────────
                $table->enum('status', [
                    'pending', 'queued', 'sending', 'sent', 'delivered',
                    'failed', 'bounced', 'retrying', 'dlq',
                ])->default('pending');
                $table->integer('retry_count')->default(0);
                $table->integer('max_retries')->default(3);
                $table->timestamp('next_retry_at')->nullable();
                $table->text('failure_reason')->nullable();
                $table->string('external_id', 200)->nullable();  // Provider message ID

                // ── Rate Limiting (FR-NTF-005) ───────────────────
                $table->boolean('is_batched')->default(false);    // Part of a digest
                $table->string('batch_id', 100)->nullable();      // Grouped with other notifications
                $table->boolean('is_throttled')->default(false);  // Was throttled

                // ── Scheduling (FR-NTF-007) ──────────────────────
                $table->timestamp('scheduled_at')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('read_at')->nullable();         // For in-app

                // ── Third Party (FR-NTF-009) ─────────────────────
                $table->string('provider', 100)->nullable();      // mailgun, twilio, slack, custom
                $table->json('provider_response')->nullable();

                $table->timestamps();

                $table->index(['account_id', 'event_type', 'created_at']);
                $table->index(['user_id', 'status']);
                $table->index(['status', 'next_retry_at']);
                $table->index(['channel', 'created_at']);
                $table->index(['entity_type', 'entity_id']);
                // FKs omitted for server compatibility
            });
        }

        // ═══════════════════════════════════════════════════════════
        // 4. notification_channels — FR-NTF-002/009
        //    Account-level channel configuration
        // ═══════════════════════════════════════════════════════════
        if (! Schema::hasTable('notification_channels')) {
            Schema::create('notification_channels', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('account_id');

                $table->string('channel', 50);              // email, sms, slack, webhook, custom
                $table->string('provider', 100);             // mailgun, twilio, slack_api, custom_webhook
                $table->string('name', 200);                 // Display name

                // ── Configuration ────────────────────────────────
                $table->json('config')->nullable();          // API keys, endpoints, etc. (encrypted)
                $table->string('webhook_url', 500)->nullable();
                $table->string('webhook_secret', 500)->nullable();

                $table->boolean('is_active')->default(true);
                $table->boolean('is_verified')->default(false);
                $table->timestamp('verified_at')->nullable();

                $table->timestamps();

                $table->unique(['account_id', 'channel', 'provider'], 'ntf_channel_unique');
                // FK omitted for server compatibility
            });
        }

        // ═══════════════════════════════════════════════════════════
        // 5. notification_schedules — FR-NTF-007
        //    Digest/scheduled notification preferences
        // ═══════════════════════════════════════════════════════════
        if (! Schema::hasTable('notification_schedules')) {
            Schema::create('notification_schedules', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('account_id');
                $table->uuid('user_id')->nullable();

                $table->enum('frequency', ['immediate', 'hourly', 'daily', 'weekly'])->default('immediate');
                $table->string('time_of_day', 5)->nullable();    // HH:MM for daily/weekly
                $table->string('day_of_week', 10)->nullable();   // For weekly: monday, tuesday...
                $table->string('timezone', 50)->default('Asia/Riyadh');

                $table->json('event_types')->nullable();          // null = all events
                $table->string('channel', 50)->default('email');

                $table->boolean('is_active')->default(true);
                $table->timestamp('last_sent_at')->nullable();
                $table->timestamp('next_send_at')->nullable();

                $table->timestamps();

                $table->index(['next_send_at', 'is_active']);
                // FKs omitted for server compatibility
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_schedules');
        Schema::dropIfExists('notification_channels');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notification_templates');
    }
};
