<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Notification;
use App\Models\NotificationChannel;
use App\Models\NotificationTemplate;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * API Tests — NTF Module (FR-NTF-001→009)
 *
 * 18 tests covering all notification API endpoints.
 */
class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->account = Account::factory()->create();
        $role = Role::factory()->create(['account_id' => $this->account->id, 'slug' => 'owner']);
        $this->owner = User::factory()->create([
            'account_id' => $this->account->id,
            'role_id'    => $role->id,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-NTF-008: GET /notifications
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_get_notification_log(): void
    {
        Notification::factory()->count(3)->create([
            'account_id' => $this->account->id,
            'user_id'    => $this->owner->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/notifications');

        $response->assertOk()
            ->assertJsonPath('data.total', 3);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-NTF-001: In-App Notifications
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_get_in_app_notifications(): void
    {
        Notification::factory()->inApp()->count(2)->create([
            'account_id' => $this->account->id,
            'user_id'    => $this->owner->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/notifications/in-app');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_unread_count(): void
    {
        Notification::factory()->inApp()->count(4)->create([
            'account_id' => $this->account->id,
            'user_id'    => $this->owner->id,
            'read_at'    => null,
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/notifications/unread-count');

        $response->assertOk()
            ->assertJsonPath('data.unread_count', 4);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_mark_read(): void
    {
        $n = Notification::factory()->inApp()->create([
            'account_id' => $this->account->id,
            'user_id'    => $this->owner->id,
            'read_at'    => null,
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/notifications/{$n->id}/read");

        $response->assertOk();
        $this->assertNotNull($n->fresh()->read_at);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_mark_all_read(): void
    {
        Notification::factory()->inApp()->count(3)->create([
            'account_id' => $this->account->id,
            'user_id'    => $this->owner->id,
            'read_at'    => null,
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/notifications/read-all');

        $response->assertOk()
            ->assertJsonPath('data.marked', 3);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-NTF-003: Preferences
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_get_preferences(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/notifications/preferences');

        $response->assertOk();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_update_preferences(): void
    {
        $response = $this->actingAs($this->owner)
            ->putJson('/api/v1/notifications/preferences', [
                'preferences' => [
                    ['event_type' => 'shipment.delivered', 'channel' => 'email', 'enabled' => true],
                    ['event_type' => 'shipment.delivered', 'channel' => 'sms', 'enabled' => false],
                ],
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('notification_preferences', [
            'user_id'    => $this->owner->id,
            'event_type' => 'shipment.delivered',
            'channel'    => 'sms',
            'enabled'    => false,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_update_preferences_validates(): void
    {
        $response = $this->actingAs($this->owner)
            ->putJson('/api/v1/notifications/preferences', ['preferences' => []]);

        $response->assertStatus(422);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-NTF-004: Templates
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_list_templates(): void
    {
        NotificationTemplate::factory()->count(2)->create([
            'account_id' => $this->account->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/notifications/templates');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_create_template(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/notifications/templates', [
                'event_type' => 'shipment.created',
                'channel'    => 'email',
                'language'   => 'ar',
                'subject'    => 'شحنة جديدة',
                'body'       => 'تم إنشاء شحنتك {{tracking_number}}',
            ]);

        $response->assertStatus(201);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_update_template(): void
    {
        $t = NotificationTemplate::factory()->create(['account_id' => $this->account->id]);

        $response = $this->actingAs($this->owner)
            ->putJson("/api/v1/notifications/templates/{$t->id}", [
                'subject' => 'Updated subject',
            ]);

        $response->assertOk();
        $this->assertEquals(2, $t->fresh()->version);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_preview_template(): void
    {
        $t = NotificationTemplate::factory()->create(['account_id' => $this->account->id]);

        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/notifications/templates/{$t->id}/preview", [
                'sample_data' => ['user_name' => 'Test', 'tracking_number' => 'X'],
            ]);

        $response->assertOk()
            ->assertJsonStructure(['data' => ['subject', 'body']]);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-NTF-009: Channels
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_configure_channel(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/notifications/channels', [
                'channel'     => 'slack',
                'provider'    => 'slack_api',
                'name'        => 'Engineering',
                'webhook_url' => 'https://hooks.slack.com/test',
            ]);

        $response->assertStatus(201);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_list_channels(): void
    {
        NotificationChannel::create([
            'account_id' => $this->account->id,
            'channel'    => 'webhook',
            'provider'   => 'custom',
            'name'       => 'CRM',
            'is_active'  => true,
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/notifications/channels');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    // ═══════════════════════════════════════════════════════════
    // FR-NTF-002: Test Send
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_test_send(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/notifications/test', [
                'event_type'  => 'shipment.delivered',
                'channel'     => 'email',
                'destination' => 'test@example.com',
            ]);

        $response->assertOk();
    }

    // ═══════════════════════════════════════════════════════════
    // FR-NTF-007: Schedules
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_create_schedule(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/notifications/schedules', [
                'frequency'   => 'daily',
                'time_of_day' => '08:00',
                'channel'     => 'email',
            ]);

        $response->assertStatus(201);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_list_schedules(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/notifications/schedules');

        $response->assertOk();
    }
}
