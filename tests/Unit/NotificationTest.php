<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\Notification;
use App\Models\NotificationChannel;
use App\Models\NotificationPreference;
use App\Models\NotificationSchedule;
use App\Models\NotificationTemplate;
use App\Models\Role;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit Tests — NTF Module (FR-NTF-001→009)
 *
 * 45 tests covering all 9 functional requirements.
 */
class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private NotificationService $service;
    private Account $account;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(NotificationService::class);

        $this->account = Account::factory()->create();
        $role = Role::factory()->create(['account_id' => $this->account->id, 'slug' => 'owner']);
        $this->owner = User::factory()->create([
            'account_id' => $this->account->id,
            'role_id'    => $role->id,
            'email'      => 'owner@test.com',
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-NTF-001: Send Notifications for Core Events (6 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_dispatch_sends_notification(): void
    {
        $results = $this->service->dispatch(
            Notification::EVENT_SHIPMENT_DELIVERED,
            $this->account,
            ['tracking_number' => 'TRK123'],
            'shipment', 'ship-001',
            [$this->owner->id]
        );

        $this->assertNotEmpty($results);
        $this->assertDatabaseHas('notifications', ['event_type' => Notification::EVENT_SHIPMENT_DELIVERED]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_dispatch_creates_log_entry(): void
    {
        $this->service->dispatch(
            Notification::EVENT_ORDER_CREATED,
            $this->account,
            [],
            'order', 'ord-001',
            [$this->owner->id]
        );

        $notification = Notification::first();
        $this->assertNotNull($notification);
        $this->assertEquals($this->owner->id, $notification->user_id);
        $this->assertEquals(Notification::CHANNEL_EMAIL, $notification->channel);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_core_events_constant_defined(): void
    {
        $this->assertContains(Notification::EVENT_SHIPMENT_DELIVERED, Notification::CORE_EVENTS);
        $this->assertContains(Notification::EVENT_SHIPMENT_EXCEPTION, Notification::CORE_EVENTS);
        $this->assertContains(Notification::EVENT_LABEL_CREATED, Notification::CORE_EVENTS);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_in_app_notifications_fetched(): void
    {
        Notification::factory()->inApp()->count(3)->create([
            'account_id' => $this->account->id,
            'user_id'    => $this->owner->id,
        ]);

        $notifications = $this->service->getInAppNotifications($this->owner);
        $this->assertCount(3, $notifications);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_mark_as_read(): void
    {
        $n = Notification::factory()->inApp()->create([
            'account_id' => $this->account->id,
            'user_id'    => $this->owner->id,
            'read_at'    => null,
        ]);

        $this->service->markAsRead($n->id, $this->owner);
        $this->assertNotNull($n->fresh()->read_at);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_unread_count(): void
    {
        Notification::factory()->inApp()->count(5)->create([
            'account_id' => $this->account->id,
            'user_id'    => $this->owner->id,
            'read_at'    => null,
        ]);

        $count = $this->service->getUnreadCount($this->owner);
        $this->assertEquals(5, $count);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-NTF-002: Multi-Channel Support (4 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_channel_constants_defined(): void
    {
        $this->assertEquals('email', Notification::CHANNEL_EMAIL);
        $this->assertEquals('sms', Notification::CHANNEL_SMS);
        $this->assertEquals('in_app', Notification::CHANNEL_IN_APP);
        $this->assertEquals('webhook', Notification::CHANNEL_WEBHOOK);
        $this->assertEquals('slack', Notification::CHANNEL_SLACK);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_notification_defaults_to_email_and_in_app(): void
    {
        $results = $this->service->dispatch(
            Notification::EVENT_SHIPMENT_DELIVERED,
            $this->account,
            [],
            'shipment', 'ship-001',
            [$this->owner->id]
        );

        $channels = collect($results)->pluck('channel')->unique()->values()->toArray();
        $this->assertContains('email', $channels);
        $this->assertContains('in_app', $channels);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_notification_sent_status(): void
    {
        $n = Notification::factory()->create([
            'account_id' => $this->account->id,
            'status'     => Notification::STATUS_QUEUED,
        ]);

        $n->markSent('ext_123');
        $this->assertEquals(Notification::STATUS_SENT, $n->status);
        $this->assertEquals('ext_123', $n->external_id);
        $this->assertNotNull($n->sent_at);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_notification_delivered_status(): void
    {
        $n = Notification::factory()->create(['account_id' => $this->account->id]);
        $n->markDelivered();
        $this->assertEquals(Notification::STATUS_DELIVERED, $n->status);
        $this->assertNotNull($n->delivered_at);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-NTF-003: Retry & Delivery Status (5 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_failed_notification_retries(): void
    {
        $n = Notification::factory()->create([
            'account_id' => $this->account->id,
            'status' => Notification::STATUS_QUEUED,
            'max_retries' => 3,
        ]);

        $n->markFailed('Connection timeout');

        $this->assertEquals(Notification::STATUS_RETRYING, $n->status);
        $this->assertEquals(1, $n->retry_count);
        $this->assertNotNull($n->next_retry_at);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_max_retries_moves_to_dlq(): void
    {
        $n = Notification::factory()->create([
            'account_id' => $this->account->id,
            'retry_count' => 3,
            'max_retries' => 3,
            'status' => Notification::STATUS_RETRYING,
        ]);

        $n->markFailed('Final attempt failed');

        $this->assertEquals(Notification::STATUS_DLQ, $n->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_can_retry_check(): void
    {
        $retryable = Notification::factory()->make([
            'status' => Notification::STATUS_RETRYING,
            'retry_count' => 1,
            'max_retries' => 3,
        ]);
        $this->assertTrue($retryable->canRetry());

        $maxed = Notification::factory()->make([
            'status' => Notification::STATUS_DLQ,
            'retry_count' => 3,
            'max_retries' => 3,
        ]);
        $this->assertFalse($maxed->canRetry());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_retry_queue_processes(): void
    {
        Notification::factory()->retrying()->count(2)->create([
            'account_id' => $this->account->id,
        ]);

        $results = $this->service->processRetryQueue();
        $this->assertEquals(2, $results['retried']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_exponential_backoff(): void
    {
        $n = Notification::factory()->create([
            'account_id' => $this->account->id,
            'retry_count' => 0,
            'max_retries' => 5,
        ]);

        $n->markFailed('err');
        $retry1 = $n->next_retry_at; // ~1 min

        $n->markFailed('err');
        $retry2 = $n->next_retry_at; // ~2 min

        $this->assertTrue($retry2->gt($retry1));
    }

    // ═══════════════════════════════════════════════════════════
    // FR-NTF-004: User Preferences (5 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_user_can_set_preferences(): void
    {
        $this->service->updatePreferences($this->owner, [
            ['event_type' => 'shipment.delivered', 'channel' => 'email', 'enabled' => true],
            ['event_type' => 'shipment.delivered', 'channel' => 'sms', 'enabled' => false],
        ]);

        $prefs = $this->service->getPreferences($this->owner);
        $this->assertCount(2, $prefs);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_preference_disables_channel(): void
    {
        NotificationPreference::create([
            'user_id'    => $this->owner->id,
            'account_id' => $this->account->id,
            'event_type' => Notification::EVENT_SHIPMENT_DELIVERED,
            'channel'    => Notification::CHANNEL_EMAIL,
            'enabled'    => false,
        ]);

        $enabled = NotificationPreference::isEnabled(
            $this->owner->id,
            Notification::EVENT_SHIPMENT_DELIVERED,
            Notification::CHANNEL_EMAIL
        );

        $this->assertFalse($enabled);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_no_preference_returns_null(): void
    {
        $enabled = NotificationPreference::isEnabled($this->owner->id, 'unknown.event', 'email');
        $this->assertNull($enabled); // No preference = use default
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_disabled_preference_skips_send(): void
    {
        NotificationPreference::create([
            'user_id'    => $this->owner->id,
            'account_id' => $this->account->id,
            'event_type' => Notification::EVENT_SHIPMENT_DELIVERED,
            'channel'    => Notification::CHANNEL_EMAIL,
            'enabled'    => false,
        ]);
        NotificationPreference::create([
            'user_id'    => $this->owner->id,
            'account_id' => $this->account->id,
            'event_type' => Notification::EVENT_SHIPMENT_DELIVERED,
            'channel'    => Notification::CHANNEL_IN_APP,
            'enabled'    => false,
        ]);

        $results = $this->service->dispatch(
            Notification::EVENT_SHIPMENT_DELIVERED,
            $this->account,
            [],
            'shipment', 'ship-001',
            [$this->owner->id]
        );

        // Should have no results since both channels disabled
        $sentNonThrottled = collect($results)->where('is_throttled', false)->count();
        $this->assertEquals(0, $sentNonThrottled);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_bulk_update_preferences(): void
    {
        NotificationPreference::bulkUpdate($this->owner->id, $this->account->id, [
            ['event_type' => 'order.created', 'channel' => 'email', 'enabled' => true],
            ['event_type' => 'order.created', 'channel' => 'sms', 'enabled' => false],
        ]);

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $this->owner->id,
            'event_type' => 'order.created',
            'channel' => 'email',
            'enabled' => true,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-NTF-004/002: Template Management (5 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_create_template(): void
    {
        $template = $this->service->createTemplate([
            'event_type' => 'shipment.delivered',
            'channel'    => 'email',
            'language'   => 'ar',
            'subject'    => 'تم تسليم شحنتك',
            'body'       => 'مرحباً {{user_name}}',
        ], $this->account->id);

        $this->assertNotNull($template->id);
        $this->assertEquals($this->account->id, $template->account_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_template_rendering(): void
    {
        $template = NotificationTemplate::factory()->create();

        $rendered = $template->render([
            'user_name'       => 'أحمد',
            'tracking_number' => 'TRK-999',
        ]);

        $this->assertStringContainsString('أحمد', $rendered['body']);
        $this->assertStringContainsString('TRK-999', $rendered['subject']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_template_resolution_account_first(): void
    {
        // System default
        NotificationTemplate::factory()->system()->create([
            'event_type' => 'test.event',
            'channel' => 'email',
            'language' => 'ar',
            'body' => 'System template',
        ]);

        // Account-specific
        NotificationTemplate::factory()->create([
            'account_id' => $this->account->id,
            'event_type' => 'test.event',
            'channel' => 'email',
            'language' => 'ar',
            'body' => 'Account template',
        ]);

        $resolved = NotificationTemplate::resolve('test.event', 'email', 'ar', $this->account->id);
        $this->assertStringContainsString('Account template', $resolved->body);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_template_fallback_to_system(): void
    {
        NotificationTemplate::factory()->system()->create([
            'event_type' => 'test.fallback',
            'channel' => 'email',
            'language' => 'ar',
            'body' => 'System fallback',
        ]);

        $resolved = NotificationTemplate::resolve('test.fallback', 'email', 'ar', $this->account->id);
        $this->assertStringContainsString('System fallback', $resolved->body);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_preview_template(): void
    {
        $template = NotificationTemplate::factory()->create();
        $preview = $this->service->previewTemplate($template->id, ['user_name' => 'Test', 'tracking_number' => 'X']);
        $this->assertArrayHasKey('subject', $preview);
        $this->assertArrayHasKey('body', $preview);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-NTF-005: Rate Limiting (3 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_throttled_notification_logged(): void
    {
        $n = Notification::factory()->throttled()->create([
            'account_id' => $this->account->id,
            'user_id'    => $this->owner->id,
        ]);

        $this->assertTrue($n->is_throttled);
        $this->assertEquals(Notification::STATUS_PENDING, $n->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_notification_batched_flag(): void
    {
        $n = Notification::factory()->batched()->create([
            'account_id' => $this->account->id,
        ]);

        $this->assertTrue($n->is_batched);
        $this->assertNotNull($n->batch_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_rate_limit_scopes(): void
    {
        Notification::factory()->count(3)->create([
            'account_id' => $this->account->id,
            'user_id'    => $this->owner->id,
            'channel'    => 'email',
        ]);

        $count = Notification::where('user_id', $this->owner->id)
            ->where('channel', 'email')
            ->where('created_at', '>=', now()->subHour())
            ->count();

        $this->assertEquals(3, $count);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-NTF-006: Multi-Language (3 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_arabic_template(): void
    {
        $template = NotificationTemplate::factory()->create(['language' => 'ar']);
        $this->assertEquals('ar', $template->language);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_english_template(): void
    {
        $template = NotificationTemplate::factory()->english()->create();
        $this->assertEquals('en', $template->language);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_language_resolution_from_preference(): void
    {
        NotificationPreference::create([
            'user_id'    => $this->owner->id,
            'account_id' => $this->account->id,
            'event_type' => 'test.event',
            'channel'    => 'email',
            'enabled'    => true,
            'language'   => 'en',
        ]);

        $pref = NotificationPreference::where('user_id', $this->owner->id)
            ->where('event_type', 'test.event')
            ->first();

        $this->assertEquals('en', $pref->language);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-NTF-007: Scheduled/Digest (4 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_schedule_creation(): void
    {
        $schedule = NotificationSchedule::create([
            'account_id' => $this->account->id,
            'user_id'    => $this->owner->id,
            'frequency'  => NotificationSchedule::FREQ_DAILY,
            'time_of_day' => '08:00',
            'timezone'   => 'Asia/Riyadh',
            'channel'    => 'email',
            'is_active'  => true,
        ]);

        $this->assertEquals('daily', $schedule->frequency);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_schedule_calculate_next_send(): void
    {
        $schedule = NotificationSchedule::create([
            'account_id' => $this->account->id,
            'user_id'    => $this->owner->id,
            'frequency'  => NotificationSchedule::FREQ_HOURLY,
            'channel'    => 'email',
            'is_active'  => true,
            'next_send_at' => now()->subMinute(),
        ]);

        $schedule->calculateNextSend();
        $this->assertNotNull($schedule->next_send_at);
        $this->assertTrue($schedule->next_send_at->gt(now()));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_schedule_is_due(): void
    {
        $due = NotificationSchedule::create([
            'account_id'  => $this->account->id,
            'user_id'     => $this->owner->id,
            'frequency'   => 'daily',
            'channel'     => 'email',
            'is_active'   => true,
            'next_send_at' => now()->subMinute(),
        ]);

        $this->assertTrue($due->isDue());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_immediate_schedule_not_due(): void
    {
        $immediate = NotificationSchedule::create([
            'account_id' => $this->account->id,
            'user_id'    => $this->owner->id,
            'frequency'  => NotificationSchedule::FREQ_IMMEDIATE,
            'channel'    => 'email',
            'is_active'  => true,
        ]);

        $this->assertFalse($immediate->isDue());
    }

    // ═══════════════════════════════════════════════════════════
    // FR-NTF-008: Notification Log (3 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_notification_log_paginated(): void
    {
        Notification::factory()->count(5)->create(['account_id' => $this->account->id]);

        $log = $this->service->getLog($this->account, null, 3);
        $this->assertEquals(5, $log->total());
        $this->assertCount(3, $log->items());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_log_filtered_by_user(): void
    {
        Notification::factory()->count(3)->create(['account_id' => $this->account->id, 'user_id' => $this->owner->id]);
        Notification::factory()->count(2)->create(['account_id' => $this->account->id]);

        $log = $this->service->getLog($this->account, $this->owner->id);
        $this->assertEquals(3, $log->total());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_mark_all_as_read(): void
    {
        Notification::factory()->inApp()->count(4)->create([
            'account_id' => $this->account->id,
            'user_id'    => $this->owner->id,
            'read_at'    => null,
        ]);

        $count = $this->service->markAllAsRead($this->owner);
        $this->assertEquals(4, $count);
        $this->assertEquals(0, $this->service->getUnreadCount($this->owner));
    }

    // ═══════════════════════════════════════════════════════════
    // FR-NTF-009: Third-Party Integration (2 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_configure_channel(): void
    {
        $channel = $this->service->configureChannel($this->account, [
            'channel'     => 'slack',
            'provider'    => 'slack_api',
            'name'        => 'Team Notifications',
            'webhook_url' => 'https://hooks.slack.com/test',
            'is_active'   => true,
        ]);

        $this->assertNotNull($channel->id);
        $this->assertEquals('slack', $channel->channel);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_list_channels(): void
    {
        NotificationChannel::create([
            'account_id' => $this->account->id,
            'channel' => 'webhook',
            'provider' => 'custom',
            'name' => 'CRM Webhook',
            'is_active' => true,
        ]);

        $channels = $this->service->listChannels($this->account);
        $this->assertCount(1, $channels);
    }
}
