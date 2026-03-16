<?php

namespace Tests\Feature;

use Database\Seeders\NotificationTemplateSeeder;
use App\Models\Account;
use App\Models\Notification;
use App\Models\Shipment;
use App\Models\User;
use App\Services\ShipmentTimelineService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShipmentNotificationFanoutApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NotificationTemplateSeeder::class);
    }

    public function test_purchased_event_creates_notifications_for_same_tenant_external_users(): void
    {
        [$account, $owner, $admin] = $this->createOrganizationAudience();
        $shipment = $this->createShipment($account, $owner);

        app(ShipmentTimelineService::class)->record($shipment, [
            'event_type' => 'shipment.purchased',
            'status' => 'purchased',
            'normalized_status' => 'purchased',
            'description' => 'تم إصدار الشحنة لدى الناقل.',
            'event_at' => now(),
            'source' => 'system',
            'idempotency_key' => 'purchased:' . $shipment->id,
        ]);

        $this->assertDatabaseCount('notifications', 4);
        $this->assertDatabaseHas('notifications', [
            'account_id' => (string) $account->id,
            'user_id' => (string) $owner->id,
            'event_type' => Notification::EVENT_SHIPMENT_PURCHASED,
            'channel' => Notification::CHANNEL_IN_APP,
            'entity_type' => 'shipment',
            'entity_id' => (string) $shipment->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'account_id' => (string) $account->id,
            'user_id' => (string) $admin->id,
            'event_type' => Notification::EVENT_SHIPMENT_PURCHASED,
            'channel' => Notification::CHANNEL_IN_APP,
            'entity_type' => 'shipment',
            'entity_id' => (string) $shipment->id,
        ]);
    }

    public function test_documents_available_event_creates_notification(): void
    {
        [$account, $owner] = $this->createOrganizationAudience();
        $shipment = $this->createShipment($account, $owner);

        app(ShipmentTimelineService::class)->record($shipment, [
            'event_type' => 'carrier.documents_available',
            'status' => 'label_ready',
            'normalized_status' => 'label_ready',
            'description' => 'أصبحت مستندات الشحنة متاحة.',
            'event_at' => now(),
            'source' => 'carrier',
            'idempotency_key' => 'documents:' . $shipment->id,
        ]);

        $this->assertDatabaseHas('notifications', [
            'account_id' => (string) $account->id,
            'user_id' => (string) $owner->id,
            'event_type' => Notification::EVENT_SHIPMENT_DOCUMENTS_AVAILABLE,
            'channel' => Notification::CHANNEL_IN_APP,
        ]);
    }

    public function test_delivered_event_creates_notification_visible_in_existing_api_surface(): void
    {
        [$account, $owner] = $this->createOrganizationAudience();
        $shipment = $this->createShipment($account, $owner);

        app(ShipmentTimelineService::class)->record($shipment, [
            'event_type' => 'tracking.status_updated',
            'status' => 'delivered',
            'normalized_status' => 'delivered',
            'description' => 'تم تسليم الشحنة.',
            'event_at' => now(),
            'source' => 'carrier',
            'idempotency_key' => 'delivered:' . $shipment->id,
        ]);

        $this->grantTenantPermissions($owner, ['notifications.read'], 'shipment_notification_reader');

        $this->actingAs($owner)
            ->getJson('/api/v1/notifications/in-app')
            ->assertOk()
            ->assertJsonFragment([
                'event_type' => Notification::EVENT_SHIPMENT_DELIVERED,
                'entity_id' => (string) $shipment->id,
            ]);
    }

    public function test_exception_event_creates_notification(): void
    {
        [$account, $owner] = $this->createOrganizationAudience();
        $shipment = $this->createShipment($account, $owner);

        app(ShipmentTimelineService::class)->record($shipment, [
            'event_type' => 'tracking.status_updated',
            'status' => 'exception',
            'normalized_status' => 'exception',
            'description' => 'تعذر التسليم في المحاولة الحالية.',
            'event_at' => now(),
            'source' => 'carrier',
            'idempotency_key' => 'exception:' . $shipment->id,
        ]);

        $this->assertDatabaseHas('notifications', [
            'account_id' => (string) $account->id,
            'user_id' => (string) $owner->id,
            'event_type' => Notification::EVENT_SHIPMENT_EXCEPTION,
            'channel' => Notification::CHANNEL_IN_APP,
        ]);
    }

    public function test_cross_tenant_users_do_not_receive_or_access_notifications(): void
    {
        [$accountA, $userA] = $this->createOrganizationAudience();
        [$accountB, $userB] = $this->createOrganizationAudience();
        $shipmentB = $this->createShipment($accountB, $userB);

        app(ShipmentTimelineService::class)->record($shipmentB, [
            'event_type' => 'tracking.status_updated',
            'status' => 'delivered',
            'normalized_status' => 'delivered',
            'description' => 'تم تسليم الشحنة.',
            'event_at' => now(),
            'source' => 'carrier',
            'idempotency_key' => 'delivered:' . $shipmentB->id,
        ]);

        $this->grantTenantPermissions($userA, ['notifications.read', 'notifications.manage'], 'shipment_notification_cross_tenant_reader');

        $this->assertDatabaseMissing('notifications', [
            'account_id' => (string) $accountA->id,
            'user_id' => (string) $userA->id,
            'entity_id' => (string) $shipmentB->id,
        ]);

        $notificationId = (string) Notification::query()
            ->where('account_id', (string) $accountB->id)
            ->where('entity_id', (string) $shipmentB->id)
            ->where('channel', Notification::CHANNEL_IN_APP)
            ->value('id');

        $this->actingAs($userA)
            ->getJson('/api/v1/notifications/in-app')
            ->assertOk()
            ->assertJsonMissing(['entity_id' => (string) $shipmentB->id]);

        $this->actingAs($userA)
            ->postJson('/api/v1/notifications/' . $notificationId . '/read')
            ->assertNotFound();
    }

    /**
     * @return array{0: Account, 1: User, 2?: User}
     */
    private function createOrganizationAudience(): array
    {
        $account = Account::factory()->organization()->create([
            'name' => 'Notifications Org ' . Str::upper(Str::random(4)),
            'status' => 'active',
        ]);

        $owner = User::factory()->create([
            'account_id' => $account->id,
            'user_type' => 'external',
            'status' => 'active',
        ]);

        $admin = User::factory()->create([
            'account_id' => $account->id,
            'user_type' => 'external',
            'status' => 'active',
        ]);

        return [$account, $owner, $admin];
    }

    private function createShipment(Account $account, User $user): Shipment
    {
        $trackingNumber = 'TRK-' . Str::upper(Str::random(8));

        $attributes = [
            'account_id' => (string) $account->id,
            'user_id' => (string) $user->id,
            'status' => Shipment::STATUS_PURCHASED,
            'carrier_code' => 'fedex',
            'carrier_name' => 'FedEx',
        ];

        if (Schema::hasColumn('shipments', 'tracking_number')) {
            $attributes['tracking_number'] = $trackingNumber;
        }

        if (Schema::hasColumn('shipments', 'carrier_tracking_number')) {
            $attributes['carrier_tracking_number'] = $trackingNumber;
        }

        return Shipment::factory()->purchased()->create($attributes);
    }
}
