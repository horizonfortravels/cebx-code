<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Notification;
use App\Models\NotificationChannel;
use App\Models\NotificationTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\MailManager;
use Illuminate\Mail\Mailer;
use Illuminate\Mail\SentMessage;
use Laravel\Sanctum\Sanctum;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = Account::factory()->organization()->create();
        $this->owner = User::factory()->owner()->create([
            'account_id' => $this->account->id,
            'user_type' => 'external',
            'is_owner' => true,
            'status' => 'active',
        ]);

        $this->grantTenantPermissions($this->owner, [
            'notifications.read',
            'notifications.manage',
            'notifications.templates.manage',
            'notifications.channels.manage',
            'notifications.schedules.manage',
        ], 'organization_owner_notification_api');

        Sanctum::actingAs($this->owner);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    #[Test]
    public function test_api_get_notification_log(): void
    {
        Notification::factory()->count(3)->create([
            'account_id' => $this->account->id,
            'user_id' => $this->owner->id,
        ]);

        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data.total', 3);
    }

    #[Test]
    public function test_api_get_in_app_notifications(): void
    {
        Notification::factory()->inApp()->count(2)->create([
            'account_id' => $this->account->id,
            'user_id' => $this->owner->id,
        ]);

        $this->getJson('/api/v1/notifications/in-app')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function test_api_unread_count(): void
    {
        Notification::factory()->inApp()->count(4)->create([
            'account_id' => $this->account->id,
            'user_id' => $this->owner->id,
            'read_at' => null,
        ]);

        $this->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 4);
    }

    #[Test]
    public function test_api_mark_read(): void
    {
        $notification = Notification::factory()->inApp()->create([
            'account_id' => $this->account->id,
            'user_id' => $this->owner->id,
            'read_at' => null,
        ]);

        $this->postJson('/api/v1/notifications/' . $notification->id . '/read')
            ->assertOk();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    #[Test]
    public function test_api_mark_all_read(): void
    {
        Notification::factory()->inApp()->count(3)->create([
            'account_id' => $this->account->id,
            'user_id' => $this->owner->id,
            'read_at' => null,
        ]);

        $this->postJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.marked', 3);
    }

    #[Test]
    public function test_api_get_preferences(): void
    {
        $this->getJson('/api/v1/notifications/preferences')
            ->assertOk();
    }

    #[Test]
    public function test_api_update_preferences(): void
    {
        $this->putJson('/api/v1/notifications/preferences', [
            'preferences' => [
                ['event_type' => 'shipment.delivered', 'channel' => 'email', 'enabled' => true],
                ['event_type' => 'shipment.delivered', 'channel' => 'sms', 'enabled' => false],
            ],
        ])
            ->assertOk();

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $this->owner->id,
            'event_type' => 'shipment.delivered',
            'channel' => 'sms',
            'enabled' => false,
        ]);
    }

    #[Test]
    public function test_api_update_preferences_validates(): void
    {
        $this->putJson('/api/v1/notifications/preferences', ['preferences' => []])
            ->assertStatus(422);
    }

    #[Test]
    public function test_api_list_templates(): void
    {
        NotificationTemplate::factory()->create([
            'account_id' => $this->account->id,
            'event_type' => 'shipment.delivered',
            'channel' => 'email',
            'language' => 'ar',
        ]);

        NotificationTemplate::factory()->create([
            'account_id' => $this->account->id,
            'event_type' => 'shipment.exception',
            'channel' => 'in_app',
            'language' => 'ar',
        ]);

        $this->getJson('/api/v1/notifications/templates')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function test_api_create_template(): void
    {
        $this->postJson('/api/v1/notifications/templates', [
            'event_type' => 'shipment.created',
            'channel' => 'email',
            'language' => 'ar',
            'subject' => 'New shipment',
            'body' => 'Shipment {{tracking_number}} created',
        ])->assertStatus(201);
    }

    #[Test]
    public function test_api_update_template(): void
    {
        $template = NotificationTemplate::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $this->putJson('/api/v1/notifications/templates/' . $template->id, [
            'subject' => 'Updated subject',
        ])->assertOk();

        $this->assertEquals(2, $template->fresh()->version);
    }

    #[Test]
    public function test_api_preview_template(): void
    {
        $template = NotificationTemplate::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $this->postJson('/api/v1/notifications/templates/' . $template->id . '/preview', [
            'sample_data' => ['user_name' => 'Test', 'tracking_number' => 'X'],
        ])
            ->assertOk()
            ->assertJsonStructure(['data' => ['subject', 'body']]);
    }

    #[Test]
    public function test_api_configure_channel(): void
    {
        $this->postJson('/api/v1/notifications/channels', [
            'channel' => 'slack',
            'provider' => 'slack_api',
            'name' => 'Engineering',
            'webhook_url' => 'https://hooks.slack.com/test',
        ])->assertStatus(201);
    }

    #[Test]
    public function test_api_list_channels(): void
    {
        NotificationChannel::create([
            'account_id' => $this->account->id,
            'channel' => 'webhook',
            'provider' => 'custom',
            'name' => 'CRM',
            'is_active' => true,
        ]);

        $this->getJson('/api/v1/notifications/channels')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function test_api_test_send(): void
    {
        $sentMessage = Mockery::mock(SentMessage::class);
        $sentMessage->shouldReceive('getMessageId')->andReturn('api-notification-test');

        $mailer = Mockery::mock(Mailer::class);
        $mailer->shouldReceive('raw')->once()->andReturn($sentMessage);

        $mailManager = Mockery::mock(MailManager::class);
        $mailManager->shouldReceive('mailer')->once()->andReturn($mailer);

        $this->app->instance(MailManager::class, $mailManager);

        $this->postJson('/api/v1/notifications/test', [
            'event_type' => 'shipment.delivered',
            'channel' => 'email',
            'destination' => 'test@example.com',
        ])->assertOk();
    }

    #[Test]
    public function test_api_create_schedule(): void
    {
        $this->postJson('/api/v1/notifications/schedules', [
            'frequency' => 'daily',
            'time_of_day' => '08:00',
            'channel' => 'email',
        ])->assertStatus(201);
    }

    #[Test]
    public function test_api_list_schedules(): void
    {
        $this->getJson('/api/v1/notifications/schedules')
            ->assertOk();
    }
}
