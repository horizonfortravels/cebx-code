<?php

namespace Tests\Feature\Web;

use App\Models\Account;
use App\Models\CarrierShipment;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\User;
use App\Services\PublicTrackingService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicTrackingPortalWebTest extends TestCase
{
    public function test_valid_public_tracking_token_renders_canonical_status_and_safe_timeline(): void
    {
        $shipment = $this->createPublicShipment();
        $token = app(PublicTrackingService::class)->ensureToken($shipment->fresh());
        $maskedTracking = app(PublicTrackingService::class)->present($shipment->fresh())['tracking_number_masked'];

        $this->get('/track/' . $token)
            ->assertOk()
            ->assertSee('التتبع العام')
            ->assertSee('يمكن تتبع هذه الشحنة دون تسجيل دخول')
            ->assertSee('تم التسليم')
            ->assertSee('محطات التتبع')
            ->assertSee((string) $maskedTracking)
            ->assertSee('FedEx')
            ->assertSee('Austin, US')
            ->assertDontSee('RECIPIENT SECRET')
            ->assertDontSee('sender@example.test')
            ->assertDontSee('REF-PUBLIC-TRACK-001')
            ->assertDontSee('794699999999')
            ->assertDontSee('wallet-preflight')
            ->assertDontSee('notifications')
            ->assertDontSee('Correlation:');
    }

    public function test_invalid_or_expired_public_tracking_token_returns_not_found_without_leakage(): void
    {
        $shipment = $this->createPublicShipment();
        $token = app(PublicTrackingService::class)->ensureToken($shipment->fresh());

        $this->get('/track/NO-SUCH-PUBLIC-TOKEN')
            ->assertNotFound()
            ->assertDontSee('REF-PUBLIC-TRACK-001')
            ->assertDontSee('RECIPIENT SECRET');

        $shipment->forceFill([
            'public_tracking_expires_at' => now()->subMinute(),
        ])->save();

        $this->get('/track/' . $token)
            ->assertNotFound()
            ->assertDontSee('REF-PUBLIC-TRACK-001')
            ->assertDontSee('RECIPIENT SECRET');
    }

    public function test_private_portal_shows_public_tracking_link_after_issuance(): void
    {
        $user = $this->createPortalUser();
        $shipment = $this->createPublicShipment($user);

        $this->actingAs($user, 'web')
            ->get('/b2b/shipments/' . $shipment->id)
            ->assertOk()
            ->assertSee('data-testid="public-tracking-link"', false)
            ->assertSee('/track/', false);
    }

    private function createPortalUser(): User
    {
        $account = Account::factory()->organization()->create([
            'name' => 'Public Tracking ' . Str::upper(Str::random(4)),
            'status' => 'active',
        ]);

        $user = User::factory()->create([
            'account_id' => $account->id,
            'user_type' => 'external',
            'status' => 'active',
            'locale' => 'ar',
        ]);

        $this->grantTenantPermissions($user, [
            'shipments.read',
            'tracking.read',
            'notifications.read',
        ], 'public_tracking_portal');

        return $user;
    }

    private function createPublicShipment(?User $user = null): Shipment
    {
        $user = $user ?? $this->createPortalUser();

        $attributes = [
            'account_id' => (string) $user->account_id,
            'user_id' => (string) $user->id,
            'status' => Shipment::STATUS_PURCHASED,
            'sender_name' => 'SENDER SECRET',
            'sender_city' => 'Riyadh',
            'recipient_name' => 'RECIPIENT SECRET',
            'recipient_city' => 'Austin',
            'reference_number' => 'REF-PUBLIC-TRACK-001',
            'carrier_code' => 'fedex',
            'carrier_name' => 'FedEx',
        ];

        if (Schema::hasColumn('shipments', 'sender_email')) {
            $attributes['sender_email'] = 'sender@example.test';
        }

        if (Schema::hasColumn('shipments', 'sender_country')) {
            $attributes['sender_country'] = 'SA';
        }

        if (Schema::hasColumn('shipments', 'recipient_email')) {
            $attributes['recipient_email'] = 'recipient@example.test';
        }

        if (Schema::hasColumn('shipments', 'recipient_country')) {
            $attributes['recipient_country'] = 'US';
        }

        if (Schema::hasColumn('shipments', 'tracking_number')) {
            $attributes['tracking_number'] = '794699999999';
        }

        if (Schema::hasColumn('shipments', 'carrier_tracking_number')) {
            $attributes['carrier_tracking_number'] = '794699999999';
        }

        if (Schema::hasColumn('shipments', 'tracking_status')) {
            $attributes['tracking_status'] = Shipment::STATUS_DELIVERED;
        }

        if (Schema::hasColumn('shipments', 'tracking_updated_at')) {
            $attributes['tracking_updated_at'] = now();
        }

        $shipment = Shipment::factory()->create($attributes);

        CarrierShipment::factory()->labelReady()->create([
            'shipment_id' => (string) $shipment->id,
            'account_id' => (string) $user->account_id,
            'carrier_code' => 'fedex',
            'carrier_name' => 'FedEx',
            'tracking_number' => '794699999999',
        ]);

        ShipmentEvent::factory()->purchased()->create([
            'shipment_id' => (string) $shipment->id,
            'account_id' => (string) $user->account_id,
            'event_at' => now()->subHours(5),
        ]);

        ShipmentEvent::factory()->create([
            'shipment_id' => (string) $shipment->id,
            'account_id' => (string) $user->account_id,
            'event_type' => 'tracking.status_updated',
            'status' => 'in_transit',
            'normalized_status' => 'in_transit',
            'description' => 'In transit',
            'event_at' => now()->subHours(2),
            'payload' => [
                'location_city' => 'Memphis',
                'location_country' => 'US',
                'signatory' => 'Hidden Signatory',
                'raw_status_code' => 'DF',
            ],
        ]);

        ShipmentEvent::factory()->delivered()->create([
            'shipment_id' => (string) $shipment->id,
            'account_id' => (string) $user->account_id,
            'event_at' => now()->subHour(),
            'payload' => [
                'location_city' => 'Austin',
                'location_country' => 'US',
                'signatory' => 'Hidden Signatory',
                'raw_status_code' => 'DL',
            ],
        ]);

        return $shipment->fresh(['carrierShipment']);
    }
}
