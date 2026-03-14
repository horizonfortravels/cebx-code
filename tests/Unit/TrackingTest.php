<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\StatusMapping;
use App\Models\TrackingEvent;
use App\Models\TrackingSubscription;
use App\Models\TrackingWebhook;
use App\Models\User;
use App\Services\Carriers\DhlApiService;
use App\Services\TrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Unit Tests — TR Module (FR-TR-001→007)
 *
 * 45 tests covering all 7 functional requirements.
 */
class TrackingTest extends TestCase
{
    use RefreshDatabase;

    private TrackingService $service;
    private $dhlApiMock;
    private Account $account;
    private User $owner;
    private Shipment $shipment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dhlApiMock = Mockery::mock(DhlApiService::class);
        $this->app->instance(DhlApiService::class, $this->dhlApiMock);

        $this->service = $this->app->make(TrackingService::class);

        $this->account = Account::factory()->create();
        $role = Role::factory()->create(['account_id' => $this->account->id, 'slug' => 'owner']);
        $this->owner = $this->createUserWithRole((string) $this->account->id, (string) $role->id);

        $this->shipment = Shipment::factory()->create([
            'account_id'      => $this->account->id,
            'tracking_number' => '1234567890',
            'status'          => Shipment::STATUS_READY_FOR_PICKUP,
        ]);

        // Seed status mappings
        $this->seedMappings();
    }

    // ═══════════════════════════════════════════════════════════
    // FR-TR-001: Receive Tracking Events (6 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_webhook_processes_valid_tracking_event(): void
    {
        $payload = $this->fakeDhlWebhookPayload('1234567890', 'transit', 'In transit');
        $headers = $this->fakeHeaders();

        $result = $this->service->processWebhook($payload, $headers, '127.0.0.1');

        $this->assertEquals('processed', $result['status']);
        $this->assertEquals(1, $result['events']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_webhook_creates_tracking_event_record(): void
    {
        $payload = $this->fakeDhlWebhookPayload('1234567890', 'delivered', 'Delivered');
        $this->service->processWebhook($payload, $this->fakeHeaders(), '127.0.0.1');

        $event = TrackingEvent::where('shipment_id', $this->shipment->id)->first();
        $this->assertNotNull($event);
        $this->assertEquals('delivered', $event->raw_status);
        $this->assertEquals('webhook', $event->source);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_webhook_logs_receipt(): void
    {
        $payload = $this->fakeDhlWebhookPayload('1234567890', 'transit', 'In transit');
        $this->service->processWebhook($payload, $this->fakeHeaders(), '10.0.0.1');

        $webhook = TrackingWebhook::first();
        $this->assertNotNull($webhook);
        $this->assertEquals('10.0.0.1', $webhook->source_ip);
        $this->assertEquals('processed', $webhook->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_webhook_updates_shipment_status(): void
    {
        $payload = $this->fakeDhlWebhookPayload('1234567890', 'delivered', 'Delivered');
        $this->service->processWebhook($payload, $this->fakeHeaders(), '127.0.0.1');

        $this->shipment->refresh();
        $this->assertEquals(TrackingEvent::STATUS_DELIVERED, $this->shipment->tracking_status);
        $this->assertNotNull($this->shipment->tracking_updated_at);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_webhook_ignores_unknown_tracking_number(): void
    {
        $payload = $this->fakeDhlWebhookPayload('UNKNOWN999', 'transit', 'In transit');
        $result = $this->service->processWebhook($payload, $this->fakeHeaders(), '127.0.0.1');

        $this->assertEquals(0, $result['events']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_polling_fetches_and_processes_events(): void
    {
        $this->dhlApiMock->shouldReceive('trackShipment')
            ->with('1234567890')
            ->andReturn([
                'shipments' => [[
                    'events' => [[
                        'status' => 'picked_up',
                        'description' => 'Shipment picked up',
                        'statusCode' => 'PL',
                        'timestamp' => now()->subHour()->toIso8601String(),
                        'location' => ['address' => ['addressLocality' => 'Riyadh', 'countryCode' => 'SA']],
                    ]],
                ]],
            ]);

        $result = $this->service->pollTrackingUpdates(['1234567890']);

        $this->assertEquals(1, $result['polled']);
        $this->assertEquals(1, $result['new_events']);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-TR-002: Webhook Verification (5 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_rejects_webhook_with_invalid_signature(): void
    {
        config(['services.dhl.webhook_secret' => 'test-secret']);

        $payload = $this->fakeDhlWebhookPayload('1234567890', 'transit', 'test');
        $headers = ['x-dhl-signature' => 'invalid-signature'];

        $result = $this->service->processWebhook($payload, $headers, '127.0.0.1');

        $this->assertEquals('rejected', $result['status']);
        $this->assertEquals('invalid_signature', $result['reason']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_rejects_webhook_without_signature(): void
    {
        config(['services.dhl.webhook_secret' => 'test-secret']);

        $payload = $this->fakeDhlWebhookPayload('1234567890', 'transit', 'test');
        $result = $this->service->processWebhook($payload, [], '127.0.0.1');

        $this->assertEquals('rejected', $result['status']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_rejects_replay_attack(): void
    {
        $payload = $this->fakeDhlWebhookPayload('1234567890', 'transit', 'In transit');
        $headers = $this->fakeHeaders();
        $headers['x-dhl-message-id'] = 'unique-message-id-123';

        // First call succeeds
        $this->service->processWebhook($payload, $headers, '127.0.0.1');

        // Second call = replay
        $result = $this->service->processWebhook($payload, $headers, '127.0.0.1');

        $this->assertEquals('rejected', $result['status']);
        $this->assertEquals('replay_detected', $result['reason']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_rejects_invalid_schema(): void
    {
        $result = $this->service->processWebhook(
            ['invalid' => 'no tracking data'],
            $this->fakeHeaders(),
            '127.0.0.1'
        );

        $this->assertEquals('rejected', $result['status']);
        $this->assertEquals('invalid_schema', $result['reason']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_webhook_rejection_logged(): void
    {
        config(['services.dhl.webhook_secret' => 'secret']);
        $this->service->processWebhook(
            $this->fakeDhlWebhookPayload('1234567890', 'test', 'test'),
            ['x-dhl-signature' => 'bad'],
            '127.0.0.1'
        );

        $webhook = TrackingWebhook::first();
        $this->assertEquals('rejected', $webhook->status);
        $this->assertFalse($webhook->signature_valid);
        $this->assertNotNull($webhook->rejection_reason);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-TR-003: Deduplication & Ordering (5 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_dedup_key_generated_consistently(): void
    {
        $key1 = TrackingEvent::generateDedupKey('TRK123', 'transit', '2026-01-01T10:00:00Z', 'DXB');
        $key2 = TrackingEvent::generateDedupKey('TRK123', 'transit', '2026-01-01T10:00:00Z', 'DXB');

        $this->assertEquals($key1, $key2);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_dedup_key_differs_for_different_events(): void
    {
        $key1 = TrackingEvent::generateDedupKey('TRK123', 'transit', '2026-01-01T10:00:00Z');
        $key2 = TrackingEvent::generateDedupKey('TRK123', 'delivered', '2026-01-01T12:00:00Z');

        $this->assertNotEquals($key1, $key2);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_duplicate_event_not_stored(): void
    {
        $payload = $this->fakeDhlWebhookPayload('1234567890', 'transit', 'In transit');

        // First call
        $this->service->processWebhook($payload, $this->fakeHeaders(), '127.0.0.1');

        // Duplicate (different webhook ID but same event data)
        $headers2 = $this->fakeHeaders();
        $headers2['x-dhl-message-id'] = 'different-message-id';
        $result = $this->service->processWebhook($payload, $headers2, '127.0.0.1');

        // Should process webhook but find 0 new events (dedup)
        $events = TrackingEvent::where('shipment_id', $this->shipment->id)->get();
        $this->assertCount(1, $events);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_out_of_order_event_does_not_regress_status(): void
    {
        // First: delivered (newer event)
        $delivered = $this->fakeDhlWebhookPayload('1234567890', 'delivered', 'Delivered');
        $delivered['events'][0]['timestamp'] = now()->toIso8601String();
        $this->service->processWebhook($delivered, $this->fakeHeaders(), '127.0.0.1');

        $this->shipment->refresh();
        $this->assertEquals(TrackingEvent::STATUS_DELIVERED, $this->shipment->tracking_status);

        // Second: in_transit (older event arriving late)
        $transit = $this->fakeDhlWebhookPayload('1234567890', 'transit', 'In transit');
        $transit['events'][0]['timestamp'] = now()->subDay()->toIso8601String();
        $transit['events'][0]['statusCode'] = 'DF'; // Different status code for dedup
        $headers2 = $this->fakeHeaders();
        $headers2['x-dhl-message-id'] = 'msg-2';
        $this->service->processWebhook($transit, $headers2, '127.0.0.1');

        // Status should NOT regress to in_transit
        $this->shipment->refresh();
        $this->assertEquals(TrackingEvent::STATUS_DELIVERED, $this->shipment->tracking_status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_sequence_numbers_assigned(): void
    {
        // Event 1
        $payload1 = $this->fakeDhlWebhookPayload('1234567890', 'picked_up', 'Picked up');
        $payload1['events'][0]['timestamp'] = now()->subHours(2)->toIso8601String();
        $this->service->processWebhook($payload1, $this->fakeHeaders(), '127.0.0.1');

        // Event 2
        $payload2 = $this->fakeDhlWebhookPayload('1234567890', 'transit', 'In transit');
        $payload2['events'][0]['timestamp'] = now()->subHour()->toIso8601String();
        $headers2 = $this->fakeHeaders();
        $headers2['x-dhl-message-id'] = 'msg-2';
        $this->service->processWebhook($payload2, $headers2, '127.0.0.1');

        $events = TrackingEvent::where('shipment_id', $this->shipment->id)->orderBy('sequence_number')->get();
        $this->assertEquals(1, $events[0]->sequence_number);
        $this->assertEquals(2, $events[1]->sequence_number);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-TR-004: Status Normalization & Subscriptions (6 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_dhl_status_mapped_to_unified(): void
    {
        $payload = $this->fakeDhlWebhookPayload('1234567890', 'delivered', 'Delivered');
        $payload['events'][0]['statusCode'] = 'OK';
        $this->service->processWebhook($payload, $this->fakeHeaders(), '127.0.0.1');

        $event = TrackingEvent::where('shipment_id', $this->shipment->id)->first();
        $this->assertEquals(TrackingEvent::STATUS_DELIVERED, $event->unified_status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_unknown_status_maps_to_unknown(): void
    {
        $payload = $this->fakeDhlWebhookPayload('1234567890', 'very_unusual_status', 'Something weird');
        $this->service->processWebhook($payload, $this->fakeHeaders(), '127.0.0.1');

        $event = TrackingEvent::where('shipment_id', $this->shipment->id)->first();
        $this->assertEquals(TrackingEvent::STATUS_UNKNOWN, $event->unified_status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_status_mapping_resolve(): void
    {
        $mapping = StatusMapping::resolve('dhl', 'delivered', 'OK');
        $this->assertNotNull($mapping);
        $this->assertEquals(TrackingEvent::STATUS_DELIVERED, $mapping->unified_status);
        $this->assertTrue($mapping->is_terminal);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_subscribe_to_tracking_updates(): void
    {
        $sub = $this->service->subscribe([
            'channel'     => 'email',
            'destination' => 'test@example.com',
            'event_types' => ['delivered', 'exception'],
        ], $this->shipment, $this->owner);

        $this->assertNotNull($sub->id);
        $this->assertTrue($sub->wantsEvent('delivered'));
        $this->assertFalse($sub->wantsEvent('in_transit'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_subscriber_null_event_types_means_all(): void
    {
        $sub = TrackingSubscription::factory()->create([
            'shipment_id' => $this->shipment->id,
            'account_id'  => $this->account->id,
            'event_types' => null,
        ]);

        $this->assertTrue($sub->wantsEvent('delivered'));
        $this->assertTrue($sub->wantsEvent('in_transit'));
        $this->assertTrue($sub->wantsEvent('exception'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_unsubscribe_deactivates(): void
    {
        $sub = TrackingSubscription::factory()->create([
            'shipment_id' => $this->shipment->id,
            'account_id'  => $this->account->id,
        ]);

        $this->service->unsubscribe($sub->id);
        $this->assertFalse($sub->fresh()->is_active);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-TR-005: Timeline Display (5 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_timeline_returns_ordered_events(): void
    {
        TrackingEvent::factory()->create([
            'shipment_id' => $this->shipment->id, 'account_id' => $this->account->id,
            'unified_status' => TrackingEvent::STATUS_PICKED_UP, 'event_time' => now()->subHours(3),
        ]);
        TrackingEvent::factory()->create([
            'shipment_id' => $this->shipment->id, 'account_id' => $this->account->id,
            'unified_status' => TrackingEvent::STATUS_IN_TRANSIT, 'event_time' => now()->subHours(2),
        ]);
        TrackingEvent::factory()->delivered()->create([
            'shipment_id' => $this->shipment->id, 'account_id' => $this->account->id,
            'event_time' => now()->subHour(),
        ]);

        $timeline = $this->service->getTimeline($this->shipment);

        $this->assertCount(3, $timeline['events']);
        $this->assertEquals(TrackingEvent::STATUS_PICKED_UP, $timeline['events'][0]['status']);
        $this->assertEquals(TrackingEvent::STATUS_DELIVERED, $timeline['events'][2]['status']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_timeline_event_has_location(): void
    {
        TrackingEvent::factory()->create([
            'shipment_id' => $this->shipment->id, 'account_id' => $this->account->id,
            'location_city' => 'Dubai', 'location_country' => 'AE',
        ]);

        $timeline = $this->service->getTimeline($this->shipment);

        $this->assertStringContainsString('Dubai', $timeline['events'][0]['location']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_timeline_includes_signatory_for_delivered(): void
    {
        TrackingEvent::factory()->delivered()->create([
            'shipment_id' => $this->shipment->id, 'account_id' => $this->account->id,
            'signatory' => 'Mohammed',
        ]);

        $timeline = $this->service->getTimeline($this->shipment);

        $this->assertEquals('Mohammed', $timeline['events'][0]['signatory']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_search_by_status(): void
    {
        $this->shipment->update(['tracking_status' => TrackingEvent::STATUS_IN_TRANSIT]);
        Shipment::factory()->create(['account_id' => $this->account->id, 'tracking_status' => TrackingEvent::STATUS_DELIVERED]);

        $results = $this->service->searchByStatus($this->account, TrackingEvent::STATUS_IN_TRANSIT);

        $this->assertEquals(1, $results->total());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_search_by_tracking_number(): void
    {
        $results = $this->service->searchByStatus($this->account, null, '123456');
        $this->assertEquals(1, $results->total());
    }

    // ═══════════════════════════════════════════════════════════
    // FR-TR-006: Store Notification + Dashboard (5 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_store_notifiable_mappings(): void
    {
        $storeNotifiable = StatusMapping::storeNotifiable()->get();
        $this->assertTrue($storeNotifiable->count() > 0);

        $deliveredMapping = $storeNotifiable->firstWhere('unified_status', TrackingEvent::STATUS_DELIVERED);
        $this->assertNotNull($deliveredMapping);
        $this->assertEquals('fulfilled', $deliveredMapping->store_status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_store_notified_flag_set(): void
    {
        $this->shipment->update(['store_id' => 'some-store-id']);

        $payload = $this->fakeDhlWebhookPayload('1234567890', 'delivered', 'Delivered');
        $payload['events'][0]['statusCode'] = 'OK';
        $this->service->processWebhook($payload, $this->fakeHeaders(), '127.0.0.1');

        $event = TrackingEvent::where('shipment_id', $this->shipment->id)->first();
        $this->assertTrue($event->notified_store);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_dashboard_returns_status_counts(): void
    {
        Shipment::factory()->count(3)->create(['account_id' => $this->account->id, 'tracking_status' => TrackingEvent::STATUS_IN_TRANSIT]);
        Shipment::factory()->count(2)->create(['account_id' => $this->account->id, 'tracking_status' => TrackingEvent::STATUS_DELIVERED]);

        $dashboard = $this->service->getStatusDashboard($this->account);

        $this->assertEquals(3, $dashboard['by_status'][TrackingEvent::STATUS_IN_TRANSIT] ?? 0);
        $this->assertEquals(2, $dashboard['by_status'][TrackingEvent::STATUS_DELIVERED] ?? 0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_dashboard_calculates_delivery_rate(): void
    {
        Shipment::factory()->count(8)->create(['account_id' => $this->account->id, 'tracking_status' => TrackingEvent::STATUS_DELIVERED]);
        Shipment::factory()->count(2)->create(['account_id' => $this->account->id, 'tracking_status' => TrackingEvent::STATUS_IN_TRANSIT]);

        $dashboard = $this->service->getStatusDashboard($this->account);

        // 8 delivered out of 11 total (including original shipment)
        $this->assertGreaterThan(0, $dashboard['delivery_rate']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_dashboard_shows_open_exceptions(): void
    {
        ShipmentException::factory()->count(3)->create([
            'account_id' => $this->account->id,
            'shipment_id' => $this->shipment->id,
            'status' => ShipmentException::STATUS_OPEN,
        ]);

        $dashboard = $this->service->getStatusDashboard($this->account);
        $this->assertEquals(3, $dashboard['open_exceptions']);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-TR-007: Exception Management (8 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_exception_created_for_exception_event(): void
    {
        $payload = $this->fakeDhlWebhookPayload('1234567890', 'exception', 'Delivery failed - address issue');
        $payload['events'][0]['statusCode'] = 'NH';
        $this->service->processWebhook($payload, $this->fakeHeaders(), '127.0.0.1');

        $exception = ShipmentException::where('shipment_id', $this->shipment->id)->first();
        $this->assertNotNull($exception);
        $this->assertEquals(ShipmentException::STATUS_OPEN, $exception->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_exception_classified_correctly(): void
    {
        $payload = $this->fakeDhlWebhookPayload('1234567890', 'exception', 'Wrong address - cannot deliver');
        $payload['events'][0]['statusCode'] = 'NH';
        $this->service->processWebhook($payload, $this->fakeHeaders(), '127.0.0.1');

        $exception = ShipmentException::where('shipment_id', $this->shipment->id)->first();
        $this->assertEquals('ADDRESS_ISSUE', $exception->exception_code);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_exception_has_suggested_action(): void
    {
        $exception = ShipmentException::fromTrackingEvent(
            TrackingEvent::factory()->exception()->create([
                'shipment_id' => $this->shipment->id,
                'account_id'  => $this->account->id,
            ]),
            'CUSTOMS_HOLD'
        );

        $this->assertNotNull($exception->suggested_action);
        $this->assertEquals('medium', $exception->priority);
        $this->assertTrue($exception->requires_customer_action);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_acknowledge_exception(): void
    {
        $exception = ShipmentException::factory()->create([
            'shipment_id' => $this->shipment->id,
            'account_id'  => $this->account->id,
        ]);

        $result = $this->service->acknowledgeException($exception->id, $this->owner);

        $this->assertEquals(ShipmentException::STATUS_ACKNOWLEDGED, $result->status);
        $this->assertNotNull($result->acknowledged_at);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_resolve_exception(): void
    {
        $exception = ShipmentException::factory()->create([
            'shipment_id' => $this->shipment->id,
            'account_id'  => $this->account->id,
        ]);

        $result = $this->service->resolveException($exception->id, 'Re-delivered successfully', $this->owner);

        $this->assertEquals(ShipmentException::STATUS_RESOLVED, $result->status);
        $this->assertEquals('Re-delivered successfully', $result->resolution_notes);
        $this->assertNotNull($result->resolved_at);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_escalate_exception(): void
    {
        $exception = ShipmentException::factory()->create([
            'shipment_id' => $this->shipment->id,
            'account_id'  => $this->account->id,
        ]);

        $exception->escalate();

        $this->assertEquals(ShipmentException::STATUS_ESCALATED, $exception->status);
        $this->assertEquals(ShipmentException::PRIORITY_CRITICAL, $exception->priority);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_get_exceptions_for_shipment(): void
    {
        ShipmentException::factory()->count(3)->create([
            'shipment_id' => $this->shipment->id,
            'account_id'  => $this->account->id,
        ]);

        $exceptions = $this->service->getExceptions($this->shipment);
        $this->assertCount(3, $exceptions);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_open_exceptions_scope(): void
    {
        ShipmentException::factory()->count(2)->create([
            'shipment_id' => $this->shipment->id,
            'account_id'  => $this->account->id,
            'status'      => ShipmentException::STATUS_OPEN,
        ]);
        ShipmentException::factory()->resolved()->create([
            'shipment_id' => $this->shipment->id,
            'account_id'  => $this->account->id,
        ]);

        $open = ShipmentException::open()->get();
        $this->assertCount(2, $open);
    }

    // ═══════════════════════════════════════════════════════════
    // Model Tests (5 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_tracking_event_is_terminal(): void
    {
        $event = TrackingEvent::factory()->delivered()->make();
        $this->assertTrue($event->isTerminal());

        $event2 = TrackingEvent::factory()->make(['unified_status' => TrackingEvent::STATUS_IN_TRANSIT]);
        $this->assertFalse($event2->isTerminal());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_tracking_event_to_timeline(): void
    {
        $event = TrackingEvent::factory()->create([
            'shipment_id'     => $this->shipment->id,
            'account_id'      => $this->account->id,
            'location_city'   => 'Jeddah',
            'location_country' => 'SA',
        ]);

        $timeline = $event->toTimeline();
        $this->assertArrayHasKey('status', $timeline);
        $this->assertArrayHasKey('event_time', $timeline);
        $this->assertStringContainsString('Jeddah', $timeline['location']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_webhook_marking_lifecycle(): void
    {
        $wh = TrackingWebhook::factory()->create(['status' => TrackingWebhook::STATUS_RECEIVED]);
        $wh->markValidated();
        $this->assertEquals('validated', $wh->status);

        $wh->markProcessed(5, 150);
        $this->assertEquals('processed', $wh->status);
        $this->assertEquals(5, $wh->events_extracted);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_subscription_notification_recording(): void
    {
        $sub = TrackingSubscription::factory()->create([
            'shipment_id' => $this->shipment->id,
            'account_id'  => $this->account->id,
        ]);

        $sub->recordNotification();
        $this->assertEquals(1, $sub->fresh()->notifications_sent);
        $this->assertNotNull($sub->fresh()->last_notified_at);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_exception_from_tracking_event_factory(): void
    {
        $event = TrackingEvent::factory()->exception()->create([
            'shipment_id' => $this->shipment->id,
            'account_id'  => $this->account->id,
            'raw_description' => 'Package damaged during transport',
        ]);

        $exception = ShipmentException::fromTrackingEvent($event, 'DAMAGED_PACKAGE');

        $this->assertEquals('DAMAGED_PACKAGE', $exception->exception_code);
        $this->assertEquals('critical', $exception->priority);
    }

    // ═══════════════════════════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════════════════════════

    private function seedMappings(): void
    {
        (new \Database\Seeders\DhlStatusMappingSeeder)->run();
    }

    private function fakeDhlWebhookPayload(string $trackingNumber, string $status, string $description): array
    {
        return [
            'trackingNumber' => $trackingNumber,
            'events' => [[
                'trackingNumber' => $trackingNumber,
                'status' => $status,
                'description' => $description,
                'statusCode' => $status,
                'timestamp' => now()->toIso8601String(),
                'location' => [
                    'address' => ['addressLocality' => 'Riyadh', 'countryCode' => 'SA'],
                ],
            ]],
        ];
    }

    private function fakeHeaders(): array
    {
        return [
            'x-dhl-signature' => hash_hmac('sha256', '{}', config('services.dhl.webhook_secret', '')),
            'x-dhl-message-id' => \Illuminate\Support\Str::uuid()->toString(),
            'content-type' => 'application/json',
        ];
    }
}
