<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Account;
use App\Models\User;
use App\Models\Role;
use App\Models\Store;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\WebhookEvent;
use App\Models\AuditLog;
use App\Services\OrderService;
use App\Services\AuditService;
use App\Services\Platforms\ShopifyAdapter;
use App\Services\Platforms\WooCommerceAdapter;
use App\Services\Platforms\PlatformAdapterFactory;
use App\Exceptions\BusinessException;
use Tests\Concerns\InteractsWithStrictRbac;

/**
 * ST Module Unit Tests — FR-ST-001 through FR-ST-010 (28 tests)
 */
class OrderTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithStrictRbac;

    protected OrderService $service;
    protected Account $account;
    protected User $owner;
    protected User $manager;
    protected User $member;
    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        AuditService::resetRequestId();
        $this->service = app(OrderService::class);

        $this->account = Account::factory()->create();
        $this->owner = User::factory()->create([
            'account_id' => $this->account->id,
            'is_owner'   => true,
        ]);

        $managerRole = $this->createTenantRoleWithPermissions(
            (string) $this->account->id,
            ['orders.manage', 'stores.manage'],
            'orders_manager'
        );
        $this->manager = $this->createUserWithRole((string) $this->account->id, (string) $managerRole->id, [
            'is_owner' => false,
        ]);

        $this->member = User::factory()->create([
            'account_id' => $this->account->id,
            'is_owner'   => false,
        ]);

        $this->store = Store::factory()->create([
            'account_id' => $this->account->id,
            'platform'   => 'manual',
            'status'     => 'active',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // FR-ST-007: Manual Order Creation
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_create_manual_order()
    {
        $order = $this->service->createManualOrder(
            $this->account->id, $this->store->id,
            $this->validOrderData(),
            $this->owner
        );

        $this->assertNotNull($order->id);
        $this->assertEquals('manual', $order->source);
        $this->assertEquals($this->account->id, $order->account_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function manager_can_create_manual_order()
    {
        $order = $this->service->createManualOrder(
            $this->account->id, $this->store->id,
            $this->validOrderData(),
            $this->manager
        );

        $this->assertNotNull($order->id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function member_cannot_create_order()
    {
        $this->expectException(BusinessException::class);
        $this->service->createManualOrder(
            $this->account->id, $this->store->id,
            $this->validOrderData(),
            $this->member
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function manual_order_creates_items()
    {
        $data = $this->validOrderData();
        $data['items'] = [
            ['name' => 'Product A', 'quantity' => 2, 'unit_price' => 50, 'sku' => 'SKU-A'],
            ['name' => 'Product B', 'quantity' => 1, 'unit_price' => 100, 'sku' => 'SKU-B'],
        ];

        $order = $this->service->createManualOrder(
            $this->account->id, $this->store->id, $data, $this->owner
        );

        $this->assertEquals(2, $order->items->count());
        $this->assertEquals(3, $order->items_count);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function manual_order_calculates_totals()
    {
        $data = $this->validOrderData();
        $data['items'] = [
            ['name' => 'Item', 'quantity' => 3, 'unit_price' => 100, 'weight' => 0.5],
        ];
        $data['shipping_cost'] = 30;

        $order = $this->service->createManualOrder(
            $this->account->id, $this->store->id, $data, $this->owner
        );

        $this->assertEquals(300, (float) $order->subtotal);
        $this->assertEquals(330, (float) $order->total_amount);
        $this->assertEquals(1.5, (float) $order->total_weight);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function manual_order_is_audit_logged()
    {
        $this->service->createManualOrder(
            $this->account->id, $this->store->id,
            $this->validOrderData(),
            $this->owner
        );

        $log = AuditLog::withoutGlobalScopes()->where('action', 'order.created')->first();
        $this->assertNotNull($log);
    }

    // ═══════════════════════════════════════════════════════════════
    // FR-ST-005: Deduplication
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function duplicate_external_order_id_rejected()
    {
        $data = $this->validOrderData();
        $data['external_order_id'] = 'UNIQUE-123';

        $this->service->createManualOrder(
            $this->account->id, $this->store->id, $data, $this->owner
        );

        $this->expectException(BusinessException::class);
        $this->service->createManualOrder(
            $this->account->id, $this->store->id, $data, $this->owner
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function same_external_id_allowed_in_different_stores()
    {
        $store2 = Store::factory()->create([
            'account_id' => $this->account->id,
            'platform'   => 'manual',
        ]);

        $data = $this->validOrderData();
        $data['external_order_id'] = 'SHARED-ID';

        $order1 = $this->service->createManualOrder(
            $this->account->id, $this->store->id, $data, $this->owner
        );
        $order2 = $this->service->createManualOrder(
            $this->account->id, $store2->id, $data, $this->owner
        );

        $this->assertNotEquals($order1->id, $order2->id);
    }

    // ═══════════════════════════════════════════════════════════════
    // FR-ST-008: Smart Rules Evaluation
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function complete_order_goes_to_ready()
    {
        $order = $this->service->createManualOrder(
            $this->account->id, $this->store->id,
            $this->validOrderData(),
            $this->owner
        );

        $this->assertEquals(Order::STATUS_READY, $order->status);
        $this->assertTrue($order->auto_ship_eligible);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function missing_address_puts_order_on_hold()
    {
        $data = $this->validOrderData();
        $data['shipping_address_line_1'] = null;
        $data['shipping_city'] = null;
        $data['shipping_country'] = null;

        $order = $this->service->createManualOrder(
            $this->account->id, $this->store->id, $data, $this->owner
        );

        $this->assertEquals(Order::STATUS_ON_HOLD, $order->status);
        $this->assertFalse($order->auto_ship_eligible);
        $this->assertStringContains('عنوان شحن غير مكتمل', $order->hold_reason);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function high_value_order_goes_on_hold()
    {
        $data = $this->validOrderData();
        $data['items'] = [['name' => 'Expensive Item', 'quantity' => 1, 'unit_price' => 6000]];

        $order = $this->service->createManualOrder(
            $this->account->id, $this->store->id, $data, $this->owner
        );

        $this->assertEquals(Order::STATUS_ON_HOLD, $order->status);
        $this->assertStringContains('قيمة عالية', $order->hold_reason);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function rules_evaluation_log_is_saved()
    {
        $order = $this->service->createManualOrder(
            $this->account->id, $this->store->id,
            $this->validOrderData(),
            $this->owner
        );

        $this->assertNotNull($order->rule_evaluation_log);
        $this->assertIsArray($order->rule_evaluation_log);
    }

    // ═══════════════════════════════════════════════════════════════
    // Order List & Filter
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function list_orders_returns_account_orders()
    {
        Order::factory()->count(3)->create([
            'account_id' => $this->account->id,
            'store_id'   => $this->store->id,
        ]);

        $result = $this->service->listOrders($this->account->id);

        $this->assertEquals(3, $result['total']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function list_orders_filters_by_status()
    {
        Order::factory()->ready()->count(2)->create([
            'account_id' => $this->account->id,
            'store_id'   => $this->store->id,
        ]);
        Order::factory()->shipped()->create([
            'account_id' => $this->account->id,
            'store_id'   => $this->store->id,
        ]);

        $result = $this->service->listOrders($this->account->id, ['status' => 'ready']);

        $this->assertEquals(2, $result['total']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function list_orders_filters_by_store()
    {
        $store2 = Store::factory()->create(['account_id' => $this->account->id]);
        Order::factory()->create(['account_id' => $this->account->id, 'store_id' => $this->store->id]);
        Order::factory()->create(['account_id' => $this->account->id, 'store_id' => $store2->id]);

        $result = $this->service->listOrders($this->account->id, ['store_id' => $this->store->id]);

        $this->assertEquals(1, $result['total']);
    }

    // ═══════════════════════════════════════════════════════════════
    // Status Management
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_transition_pending_to_ready()
    {
        $order = Order::factory()->create([
            'account_id' => $this->account->id,
            'store_id'   => $this->store->id,
            'status'     => Order::STATUS_PENDING,
        ]);

        $updated = $this->service->updateOrderStatus(
            $this->account->id, $order->id, 'ready', $this->owner
        );

        $this->assertEquals('ready', $updated->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_transition_shipped_to_pending()
    {
        $order = Order::factory()->shipped()->create([
            'account_id' => $this->account->id,
            'store_id'   => $this->store->id,
        ]);

        $this->expectException(BusinessException::class);
        $this->service->updateOrderStatus(
            $this->account->id, $order->id, 'pending', $this->owner
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_cancel_pending_order()
    {
        $order = Order::factory()->create([
            'account_id' => $this->account->id,
            'store_id'   => $this->store->id,
        ]);

        $cancelled = $this->service->cancelOrder(
            $this->account->id, $order->id, $this->owner, 'Test cancellation'
        );

        $this->assertEquals(Order::STATUS_CANCELLED, $cancelled->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_cancel_shipped_order()
    {
        $order = Order::factory()->shipped()->create([
            'account_id' => $this->account->id,
            'store_id'   => $this->store->id,
        ]);

        $this->expectException(BusinessException::class);
        $this->service->cancelOrder($this->account->id, $order->id, $this->owner);
    }

    // ═══════════════════════════════════════════════════════════════
    // FR-ST-001: Store Connection
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function manual_store_connection_always_succeeds()
    {
        $result = $this->service->testStoreConnection($this->store, $this->owner);

        $this->assertTrue($result['success']);
        $this->assertEquals('connected', $this->store->fresh()->connection_status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function shopify_connection_fails_without_credentials()
    {
        $shopifyStore = Store::factory()->create([
            'account_id'        => $this->account->id,
            'platform'          => 'shopify',
            'connection_config' => [],
        ]);

        $result = $this->service->testStoreConnection($shopifyStore, $this->owner);

        $this->assertFalse($result['success']);
        $this->assertEquals('error', $shopifyStore->fresh()->connection_status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function shopify_connection_succeeds_with_credentials()
    {
        $shopifyStore = Store::factory()->create([
            'account_id'        => $this->account->id,
            'platform'          => 'shopify',
            'connection_config' => ['access_token' => 'shpat_test', 'shop_domain' => 'test.myshopify.com'],
        ]);

        $result = $this->service->testStoreConnection($shopifyStore, $this->owner);

        $this->assertTrue($result['success']);
        $this->assertEquals('connected', $shopifyStore->fresh()->connection_status);
    }

    // ═══════════════════════════════════════════════════════════════
    // FR-ST-004: Platform Adapters
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function shopify_adapter_transforms_order()
    {
        $adapter = new ShopifyAdapter();
        $rawOrder = $this->sampleShopifyOrder();
        $store = Store::factory()->make(['platform' => 'shopify']);

        $result = $adapter->transformOrder($rawOrder, $store);

        $this->assertEquals('1234567890', $result['external_order_id']);
        $this->assertEquals('shopify', $result['source']);
        $this->assertEquals('Ahmed Ali', $result['customer_name']);
        $this->assertCount(1, $result['items']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function woocommerce_adapter_transforms_order()
    {
        $adapter = new WooCommerceAdapter();
        $rawOrder = $this->sampleWooCommerceOrder();
        $store = Store::factory()->make(['platform' => 'woocommerce']);

        $result = $adapter->transformOrder($rawOrder, $store);

        $this->assertEquals('9876', $result['external_order_id']);
        $this->assertEquals('woocommerce', $result['source']);
        $this->assertNotEmpty($result['customer_name']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function platform_factory_resolves_correct_adapter()
    {
        $shopifyStore = Store::factory()->make(['platform' => 'shopify']);
        $wooStore = Store::factory()->make(['platform' => 'woocommerce']);

        $this->assertInstanceOf(ShopifyAdapter::class, PlatformAdapterFactory::make($shopifyStore));
        $this->assertInstanceOf(WooCommerceAdapter::class, PlatformAdapterFactory::make($wooStore));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function platform_factory_throws_for_unsupported()
    {
        $manualStore = Store::factory()->make(['platform' => 'manual']);

        $this->expectException(\InvalidArgumentException::class);
        PlatformAdapterFactory::make($manualStore);
    }

    // ═══════════════════════════════════════════════════════════════
    // Order Stats
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function order_stats_returns_counts()
    {
        Order::factory()->count(2)->create(['account_id' => $this->account->id, 'store_id' => $this->store->id, 'status' => 'pending']);
        Order::factory()->ready()->create(['account_id' => $this->account->id, 'store_id' => $this->store->id]);
        Order::factory()->shipped()->create(['account_id' => $this->account->id, 'store_id' => $this->store->id]);

        $stats = $this->service->getOrderStats($this->account->id);

        $this->assertEquals(4, $stats['total']);
        $this->assertEquals(2, $stats['pending']);
        $this->assertEquals(1, $stats['ready']);
        $this->assertEquals(1, $stats['shipped']);
    }

    // ═══════════════════════════════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════════════════════════════

    private function validOrderData(): array
    {
        return [
            'customer_name'           => 'Ahmed Ali',
            'customer_email'          => 'ahmed@test.com',
            'customer_phone'          => '+966501234567',
            'shipping_name'           => 'Ahmed Ali',
            'shipping_phone'          => '+966501234567',
            'shipping_address_line_1' => '123 Main St',
            'shipping_city'           => 'Riyadh',
            'shipping_country'        => 'SA',
            'shipping_postal_code'    => '12345',
            'items' => [
                ['name' => 'Test Product', 'quantity' => 1, 'unit_price' => 100, 'sku' => 'TST-001', 'weight' => 0.5],
            ],
        ];
    }

    private function sampleShopifyOrder(): array
    {
        return [
            'id' => 1234567890,
            'order_number' => 1001,
            'name' => '#1001',
            'customer' => [
                'first_name' => 'Ahmed',
                'last_name'  => 'Ali',
                'email'      => 'ahmed@test.com',
                'phone'      => '+966501234567',
            ],
            'shipping_address' => [
                'first_name' => 'Ahmed',
                'last_name'  => 'Ali',
                'phone'      => '+966501234567',
                'address1'   => '123 Main St',
                'city'       => 'Riyadh',
                'province'   => 'Riyadh',
                'zip'        => '12345',
                'country_code' => 'SA',
            ],
            'subtotal_price' => '100.00',
            'total_tax'      => '15.00',
            'total_price'    => '140.00',
            'total_discounts' => '0.00',
            'currency'       => 'SAR',
            'shipping_lines' => [['price' => '25.00']],
            'line_items'     => [
                ['id' => 111, 'title' => 'Test Product', 'quantity' => 1, 'price' => '100.00', 'sku' => 'SKU1', 'grams' => 500],
            ],
            'created_at' => '2026-02-10T10:00:00+03:00',
            'updated_at' => '2026-02-10T10:00:00+03:00',
        ];
    }

    private function sampleWooCommerceOrder(): array
    {
        return [
            'id'       => 9876,
            'number'   => '9876',
            'billing'  => ['first_name' => 'Fatima', 'last_name' => 'Hassan', 'email' => 'fatima@test.com', 'phone' => '+966509876543'],
            'shipping' => ['first_name' => 'Fatima', 'last_name' => 'Hassan', 'address_1' => '456 Street', 'city' => 'Jeddah', 'state' => 'MK', 'postcode' => '21577', 'country' => 'SA'],
            'subtotal' => '200.00',
            'shipping_total' => '30.00',
            'total_tax'      => '30.00',
            'discount_total' => '0.00',
            'total'          => '260.00',
            'currency'       => 'SAR',
            'line_items'     => [
                ['id' => 55, 'name' => 'Widget', 'quantity' => 2, 'price' => '100.00', 'total' => '200.00', 'sku' => 'WDG-1', 'meta_data' => []],
            ],
            'date_created'  => '2026-02-10T10:00:00',
            'date_modified' => '2026-02-10T10:00:00',
        ];
    }

    /**
     * @param string $needle
     * @param string $haystack
     */
    private function assertStringContains(string $needle, ?string $haystack): void
    {
        $this->assertNotNull($haystack);
        $this->assertTrue(str_contains($haystack, $needle), "Failed asserting that '{$haystack}' contains '{$needle}'");
    }
}
