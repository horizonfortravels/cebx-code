<?php

namespace Tests\Feature\Web;

use App\Models\Account;
use App\Models\CarrierDocument;
use App\Models\CarrierShipment;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ShipmentTimelineWebTest extends TestCase
{
    #[DataProvider('portalRouteProvider')]
    public function test_browser_shipment_page_shows_timeline_and_current_status(
        string $accountType,
        string $persona,
        string $routePrefix
    ): void {
        $user = $this->createTimelineUser($accountType, $persona);
        $shipment = $this->createIssuedShipmentWithTimeline($user);

        $this->actingAs($user, 'web')
            ->get($routePrefix . $shipment->id)
            ->assertOk()
            ->assertSee('الحالة الزمنية للشحنة')
            ->assertSee('الحالة المعيارية الحالية')
            ->assertSee('تم التسليم')
            ->assertSee('التسلسل الزمني')
            ->assertSee('أصبحت مستندات الشحنة متاحة');
    }

    public function test_cross_tenant_shipment_timeline_page_returns_404(): void
    {
        $userA = $this->createTimelineUser('organization', 'organization_owner');
        $userB = $this->createTimelineUser('organization', 'organization_owner');
        $shipmentB = $this->createIssuedShipmentWithTimeline($userB);

        $this->actingAs($userA, 'web')
            ->get('/b2b/shipments/' . $shipmentB->id)
            ->assertNotFound();
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function portalRouteProvider(): array
    {
        return [
            'b2c_individual' => ['individual', 'individual', '/b2c/shipments/'],
            'b2b_owner' => ['organization', 'organization_owner', '/b2b/shipments/'],
        ];
    }

    private function createTimelineUser(string $accountType, string $persona): User
    {
        $account = $accountType === 'individual'
            ? Account::factory()->individual()->create([
                'name' => 'B2C Timeline ' . Str::upper(Str::random(4)),
                'status' => 'active',
            ])
            : Account::factory()->organization()->create([
                'name' => 'B2B Timeline ' . Str::upper(Str::random(4)),
                'status' => 'active',
            ]);

        $user = User::factory()->create([
            'account_id' => $account->id,
            'user_type' => 'external',
            'status' => 'active',
        ]);

        $this->grantTenantPermissions($user, ['shipments.read', 'tracking.read'], 'shipment_timeline_web_' . $persona);

        return $user;
    }

    private function createIssuedShipmentWithTimeline(User $user): Shipment
    {
        $attributes = [
            'account_id' => (string) $user->account_id,
            'user_id' => (string) $user->id,
            'status' => Shipment::STATUS_PURCHASED,
            'tracking_status' => 'delivered',
            'tracking_updated_at' => now(),
            'sender_name' => 'Sender',
            'recipient_name' => 'Recipient',
        ];

        $trackingNumber = 'TRK-WEB-' . Str::upper(Str::random(6));
        if (Schema::hasColumn('shipments', 'tracking_number')) {
            $attributes['tracking_number'] = $trackingNumber;
        }

        if (Schema::hasColumn('shipments', 'carrier_tracking_number')) {
            $attributes['carrier_tracking_number'] = $trackingNumber;
        }

        $shipment = Shipment::factory()->create($attributes);

        $carrierShipment = CarrierShipment::factory()->labelReady()->create([
            'shipment_id' => (string) $shipment->id,
            'account_id' => (string) $user->account_id,
            'carrier_code' => 'fedex',
            'carrier_name' => 'FedEx',
            'tracking_number' => (string) ($shipment->tracking_number ?? $shipment->carrier_tracking_number),
        ]);

        CarrierDocument::factory()->create([
            'carrier_shipment_id' => (string) $carrierShipment->id,
            'shipment_id' => (string) $shipment->id,
            'carrier_code' => 'fedex',
        ]);

        ShipmentEvent::factory()->purchased()->create([
            'shipment_id' => (string) $shipment->id,
            'account_id' => (string) $user->account_id,
            'event_at' => now()->subHours(3),
        ]);

        ShipmentEvent::factory()->labelReady()->create([
            'shipment_id' => (string) $shipment->id,
            'account_id' => (string) $user->account_id,
            'event_at' => now()->subHours(2),
        ]);

        ShipmentEvent::factory()->create([
            'shipment_id' => (string) $shipment->id,
            'account_id' => (string) $user->account_id,
            'event_type' => 'tracking.status_updated',
            'normalized_status' => 'out_for_delivery',
            'status' => 'out_for_delivery',
            'description' => 'خرجت الشحنة للتسليم',
            'event_at' => now()->subHour(),
            'payload' => ['carrier_code' => 'fedex'],
        ]);

        ShipmentEvent::factory()->delivered()->create([
            'shipment_id' => (string) $shipment->id,
            'account_id' => (string) $user->account_id,
            'event_type' => 'tracking.status_updated',
            'event_at' => now(),
        ]);

        return $shipment->fresh(['carrierShipment', 'selectedRateOption', 'rateQuote.selectedOption']);
    }
}
