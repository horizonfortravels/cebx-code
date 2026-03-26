<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\ShipmentEvent;
use App\Models\StatusMapping;
use App\Models\TrackingEvent;
use App\Models\TrackingSubscription;
use App\Models\User;
use App\Services\Carriers\DhlApiService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;
use Tests\Concerns\InteractsWithStrictRbac;

/**
 * API Tests — TR Module (FR-TR-001→007)
 *
 * 20 tests covering all tracking API endpoints.
 */
class TrackingApiTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithStrictRbac;

    /**
     * DDL compatibility helpers in this class require running without transactions.
     *
     * @var array<int, string|null>
     */
    protected $connectionsToTransact = [];

    private Account $account;
    private User $owner;
    private Shipment $shipment;
    private string $trackingNumber;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureTrackingSchemaCompatibility();

        $this->app->instance(DhlApiService::class, Mockery::mock(DhlApiService::class));
        $this->trackingNumber = 'TRK-API-' . Str::upper(Str::random(8));

        $this->account = Account::factory()->create();
        $this->owner = User::factory()->create([
            'account_id' => $this->account->id,
            'user_type' => 'external',
        ]);
        $this->grantTenantPermissions($this->owner, [
            'shipments.read',
            'shipments.manage',
            'shipments.print_label',
            'tracking.read',
            'tracking.manage',
        ], 'tracking_owner');

        $shipmentAttributes = [
            'account_id' => $this->account->id,
            'status' => 'draft',
        ];

        if (Schema::hasColumn('shipments', 'tracking_number')) {
            $shipmentAttributes['tracking_number'] = $this->trackingNumber;
        }

        if (Schema::hasColumn('shipments', 'carrier_tracking_number')) {
            $shipmentAttributes['carrier_tracking_number'] = $this->trackingNumber;
        }

        if (Schema::hasColumn('shipments', 'tracking_status')) {
            $shipmentAttributes['tracking_status'] = defined(TrackingEvent::class . '::STATUS_PENDING')
                ? TrackingEvent::STATUS_PENDING
                : 'pending';
        }

        $this->shipment = Shipment::factory()->create($shipmentAttributes);

        (new \Database\Seeders\DhlStatusMappingSeeder)->run();
    }

    // ═══════════════════════════════════════════════════════════
    // POST /webhooks/dhl/tracking — FR-TR-001/002
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_dhl_webhook_accepted(): void
    {
        $response = $this->postJson('/api/v1/webhooks/dhl/tracking', [
            'trackingNumber' => $this->trackingNumber,
            'events' => [[
                'status' => 'transit',
                'description' => 'In transit',
                'statusCode' => 'DF',
                'timestamp' => now()->toIso8601String(),
            ]],
        ], $this->webhookHeaders());

        $response->assertOk()
            ->assertJsonPath('status', 'processed');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_dhl_webhook_persists_canonical_timeline_event(): void
    {
        $this->postJson('/api/v1/webhooks/dhl/tracking', [
            'trackingNumber' => $this->trackingNumber,
            'events' => [[
                'status' => 'transit',
                'description' => 'In transit',
                'statusCode' => 'DF',
                'timestamp' => now()->toIso8601String(),
            ]],
        ], $this->webhookHeaders())->assertOk();

        $this->assertDatabaseHas('shipment_events', [
            'shipment_id' => (string) $this->shipment->id,
            'account_id' => (string) $this->account->id,
            'event_type' => 'tracking.status_updated',
            'normalized_status' => TrackingEvent::STATUS_IN_TRANSIT,
            'source' => ShipmentEvent::SOURCE_CARRIER,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_dhl_webhook_rejected_bad_signature(): void
    {
        config(['services.dhl.webhook_secret' => 'real-secret']);

        $response = $this->postJson('/api/v1/webhooks/dhl/tracking', [
            'trackingNumber' => $this->trackingNumber,
            'events' => [['status' => 'test']],
        ], ['x-dhl-signature' => 'bad']);

        $response->assertStatus(403)
            ->assertJsonPath('status', 'rejected');
    }

    // ═══════════════════════════════════════════════════════════
    // GET /shipments/{id}/tracking/timeline — FR-TR-005
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_get_tracking_timeline(): void
    {
        ShipmentEvent::factory()->count(3)->create([
            'shipment_id' => (string) $this->shipment->id,
            'account_id'  => (string) $this->account->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson("/api/v1/shipments/{$this->shipment->id}/tracking/timeline");

        $response->assertOk()
            ->assertJsonPath('data.total_events', 3)
            ->assertJsonStructure(['data' => ['events' => [['status', 'event_time', 'location']]]]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_get_current_normalized_tracking_status(): void
    {
        ShipmentEvent::factory()->delivered()->create([
            'shipment_id' => (string) $this->shipment->id,
            'account_id' => (string) $this->account->id,
            'event_at' => now(),
        ]);

        $this->shipment->update([
            'tracking_status' => TrackingEvent::STATUS_DELIVERED,
            'tracking_updated_at' => now(),
        ]);

        $this->actingAs($this->owner)
            ->getJson("/api/v1/shipments/{$this->shipment->id}/tracking/status")
            ->assertOk()
            ->assertJsonPath('data.current_status', TrackingEvent::STATUS_DELIVERED);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_get_tracking_events(): void
    {
        ShipmentEvent::factory()->count(2)->create([
            'shipment_id' => (string) $this->shipment->id,
            'account_id' => (string) $this->account->id,
        ]);

        $this->actingAs($this->owner)
            ->getJson("/api/v1/shipments/{$this->shipment->id}/tracking/events")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_timeline_empty_for_new_shipment(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson("/api/v1/shipments/{$this->shipment->id}/tracking/timeline");

        $response->assertOk()
            ->assertJsonPath('data.total_events', 0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_cross_tenant_timeline_access_returns_404(): void
    {
        $otherAccount = Account::factory()->create();
        $otherUser = User::factory()->create([
            'account_id' => $otherAccount->id,
            'user_type' => 'external',
        ]);
        $this->grantTenantPermissions($otherUser, ['tracking.read'], 'tracking_reader_other');

        $this->actingAs($otherUser)
            ->getJson("/api/v1/shipments/{$this->shipment->id}/tracking/timeline")
            ->assertNotFound();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_same_tenant_missing_permission_gets_403_for_timeline(): void
    {
        $userWithoutPermission = User::factory()->create([
            'account_id' => $this->account->id,
            'user_type' => 'external',
        ]);

        $this->actingAs($userWithoutPermission)
            ->getJson("/api/v1/shipments/{$this->shipment->id}/tracking/timeline")
            ->assertForbidden();
    }

    // ═══════════════════════════════════════════════════════════
    // GET /tracking/search — FR-TR-005
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_search_by_status(): void
    {
        if (Schema::hasColumn('shipments', 'tracking_status')) {
            $this->shipment->update(['tracking_status' => TrackingEvent::STATUS_IN_TRANSIT]);
        }

        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/tracking/search?status=in_transit');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, $response->json('data.total'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_search_by_tracking_number(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/tracking/search?tracking_number=' . urlencode($this->trackingNumber));

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, $response->json('data.total'));
    }

    // ═══════════════════════════════════════════════════════════
    // GET /tracking/dashboard — FR-TR-006
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_tracking_dashboard(): void
    {
        $attributes = ['account_id' => $this->account->id];
        if (Schema::hasColumn('shipments', 'tracking_status')) {
            $attributes['tracking_status'] = TrackingEvent::STATUS_DELIVERED;
        }

        Shipment::factory()->count(2)->create($attributes);

        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/tracking/dashboard');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['total_shipments', 'by_status', 'open_exceptions', 'delivery_rate']]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_dashboard_with_date_filter(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/tracking/dashboard?date_from=2026-01-01&date_to=2026-12-31');

        $response->assertOk();
    }

    // ═══════════════════════════════════════════════════════════
    // POST /shipments/{id}/tracking/subscribe — FR-TR-004
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_subscribe_to_tracking(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/shipments/{$this->shipment->id}/tracking/subscribe", [
                'channel'     => 'email',
                'destination' => 'test@example.com',
                'event_types' => ['delivered', 'exception'],
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('tracking_subscriptions', [
            'shipment_id' => $this->shipment->id,
            'channel'     => 'email',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_subscribe_validates_channel(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/shipments/{$this->shipment->id}/tracking/subscribe", [
                'channel'     => 'pigeon',
                'destination' => 'nope',
            ]);

        $response->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_unsubscribe(): void
    {
        $sub = TrackingSubscription::factory()->create([
            'shipment_id' => $this->shipment->id,
            'account_id'  => $this->account->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->deleteJson("/api/v1/tracking/subscriptions/{$sub->id}");

        $response->assertOk();
        $this->assertFalse($sub->fresh()->is_active);
    }

    // ═══════════════════════════════════════════════════════════
    // GET /tracking/status-mappings — FR-TR-004
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_get_status_mappings(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/tracking/status-mappings?carrier=dhl');

        $response->assertOk();
        $this->assertGreaterThan(0, count($response->json('data')));
    }

    // ═══════════════════════════════════════════════════════════
    // Exception Endpoints — FR-TR-007
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_get_shipment_exceptions(): void
    {
        ShipmentException::factory()->count(2)->create([
            'shipment_id' => $this->shipment->id,
            'account_id'  => $this->account->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson("/api/v1/shipments/{$this->shipment->id}/exceptions");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_acknowledge_exception(): void
    {
        $exception = ShipmentException::factory()->create([
            'shipment_id' => $this->shipment->id,
            'account_id'  => $this->account->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/exceptions/{$exception->id}/acknowledge");

        $response->assertOk()
            ->assertJsonPath('data.status', 'acknowledged');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_resolve_exception(): void
    {
        $exception = ShipmentException::factory()->create([
            'shipment_id' => $this->shipment->id,
            'account_id'  => $this->account->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/exceptions/{$exception->id}/resolve", [
                'notes' => 'Issue resolved by re-delivery',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'resolved');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_escalate_exception(): void
    {
        $exception = ShipmentException::factory()->create([
            'shipment_id' => $this->shipment->id,
            'account_id'  => $this->account->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/exceptions/{$exception->id}/escalate");

        $response->assertOk()
            ->assertJsonPath('data.status', 'escalated');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_resolve_requires_notes(): void
    {
        $exception = ShipmentException::factory()->create([
            'shipment_id' => $this->shipment->id,
            'account_id'  => $this->account->id,
        ]);

        $response = $this->actingAs($this->owner)
            ->postJson("/api/v1/exceptions/{$exception->id}/resolve", []);

        $response->assertStatus(422);
    }

    // ═══════════════════════════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════════════════════════

    private function webhookHeaders(): array
    {
        return [
            'X-DHL-Signature' => hash_hmac('sha256', '{}', config('services.dhl.webhook_secret', '')),
            'X-DHL-Message-Id' => \Illuminate\Support\Str::uuid()->toString(),
        ];
    }

    private function ensureTrackingSchemaCompatibility(): void
    {
        if (!Schema::hasTable('shipments')) {
            return;
        }

        Schema::table('shipments', function (Blueprint $table): void {
            if (!Schema::hasColumn('shipments', 'tracking_number')) {
                $table->string('tracking_number')->nullable()->index();
            }

            if (!Schema::hasColumn('shipments', 'tracking_status')) {
                $table->string('tracking_status')->nullable()->index();
            }
        });
    }
}
