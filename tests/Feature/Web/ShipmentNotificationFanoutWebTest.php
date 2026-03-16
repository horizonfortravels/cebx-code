<?php

namespace Tests\Feature\Web;

use Database\Seeders\NotificationTemplateSeeder;
use App\Models\Account;
use App\Models\Shipment;
use App\Models\User;
use App\Services\ShipmentTimelineService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ShipmentNotificationFanoutWebTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NotificationTemplateSeeder::class);
    }

    #[DataProvider('audienceProvider')]
    public function test_browser_notification_surface_shows_shipment_notifications(
        string $accountType,
        string $persona
    ): void {
        $user = $this->createAudienceUser($accountType, $persona);
        $trackingNumber = 'TRK-WEB-' . Str::upper(Str::random(6));
        $attributes = [
            'account_id' => (string) $user->account_id,
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

        $shipment = Shipment::factory()->purchased()->create($attributes);

        app(ShipmentTimelineService::class)->record($shipment, [
            'event_type' => 'tracking.status_updated',
            'status' => 'delivered',
            'normalized_status' => 'delivered',
            'description' => 'تم تسليم الشحنة.',
            'event_at' => now(),
            'source' => 'carrier',
            'idempotency_key' => 'delivered:' . $shipment->id,
        ]);

        $expectedTrackingNumber = (string) ($shipment->tracking_number ?? $shipment->carrier_tracking_number);

        $this->actingAs($user, 'web')
            ->get('/notifications')
            ->assertOk()
            ->assertSee('تم تسليم الشحنة')
            ->assertSee($expectedTrackingNumber);
    }

    public static function audienceProvider(): array
    {
        return [
            'b2c_individual' => ['individual', 'individual_account_holder'],
            'b2b_organization_owner' => ['organization', 'organization_owner'],
        ];
    }

    private function createAudienceUser(string $accountType, string $persona): User
    {
        $account = $accountType === 'individual'
            ? Account::factory()->individual()->create([
                'name' => 'B2C Notifications ' . Str::upper(Str::random(4)),
                'status' => 'active',
            ])
            : Account::factory()->organization()->create([
                'name' => 'B2B Notifications ' . Str::upper(Str::random(4)),
                'status' => 'active',
            ]);

        $user = User::factory()->create([
            'account_id' => (string) $account->id,
            'user_type' => 'external',
            'status' => 'active',
        ]);

        $this->grantTenantPermissions($user, ['notifications.read', 'notifications.manage'], 'shipment_notification_web_' . $persona);

        return $user;
    }
}
